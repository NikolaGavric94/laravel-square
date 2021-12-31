<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Utils\Constants;
use stdClass;

class OrderBuilder
{
    /**
     * @var ProductBuilder
     */
    private $productBuilder;
    /**
     * @var DiscountBuilder
     */
    private $discountBuilder;
    /**
     * @var TaxesBuilder
     */
    private $taxesBuilder;

    /**
     * OrderBuilder constructor.
     */
    public function __construct()
    {
        $this->productBuilder = new ProductBuilder();
        $this->discountBuilder = new DiscountBuilder();
        $this->taxesBuilder = new TaxesBuilder();
    }

    /**
     * Build order from order copy
     * and save to database simultaneously.
     *
     * @param  Model  $order
     * @param  stdClass  $orderCopy
     * @return Model
     */
    public function buildOrderFromOrderCopy(Model $order, stdClass $orderCopy)
    {
        // Order namespace
        $orderClass = config('nikolag.connections.square.order.namespace');
        // Set payment type to square
        $order->payment_service_type = 'square';
        // Save order first
        $order->save();

        // Check if order has discounts
        if ($orderCopy->discounts->isNotEmpty()) {
            // For each discount in order
            foreach ($orderCopy->discounts as $discount) {
                // Assign to temp variable
                $scope = $discount->scope;
                // Remove temp scope attribute
                unset($discount->scope);
                // Save discount
                $discount->save();
                // If order doesn't have discount, add it
                if (! $order->hasDiscount($discount)) {
                    $order->discounts()->attach($discount->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => $scope]);
                }
            }
        }

        // Check if order has taxes
        if ($orderCopy->taxes->isNotEmpty()) {
            // For each tax in order
            foreach ($orderCopy->taxes as $tax) {
                // Assign to temp variable
                $scope = $tax->scope;
                // Remove temp scope attribute
                unset($tax->scope);
                // Save tax
                $tax->save();
                // If order doesn't have tax, add it
                if (! $order->hasTax($tax)) {
                    $order->taxes()->attach($tax->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => $scope]);
                }
            }
        }

        // Check if order has products
        if ($orderCopy->products->isNotEmpty()) {
            // For each product in order
            foreach ($orderCopy->products as $product) {
                // If order doesn't have product
                if (! $order->hasProduct($product)) {
                    // Create intermediate table
                    $productPivot = $product->pivot;
                    // Create discounts
                    $discounts = $product->discounts;
                    // Create taxes
                    $taxes = $product->taxes;
                    // Remove because laravel doesn't recognize it because its Collection/array
                    unset($product->pivot);
                    unset($product->discounts);
                    unset($product->taxes);
                    // Save product
                    $product->save();

                    $product->pivot = $productPivot;
                    // Associate product with it
                    $productPivot->product()->associate($product);
                    // Associate order with it
                    $productPivot->order()->associate($order);
                    // Save intermediate model
                    $productPivot->save();

                    // For each discount in product
                    foreach ($discounts as $discount) {
                        // Remove temp scope attribute
                        unset($discount->scope);
                        // Save discount
                        $discount->save();
                        // If order doesn't have discount, add it
                        if (! $order->hasDiscount($discount)) {
                            $order->discounts()->attach($discount->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
                        }
                        // If product doesn't have discount, add it
                        if (! $productPivot->hasDiscount($discount)) {
                            $productPivot->discounts()->attach($discount->id, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
                        }
                    }

                    // For each tax in product
                    foreach ($taxes as $tax) {
                        // Save tax
                        $tax->save();
                        // If order doesn't have tax, add it
                        if (! $order->hasTax($tax)) {
                            $order->taxes()->attach($tax->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
                        }
                        // If product doesn't have tax, add it
                        if (! $productPivot->hasTax($tax)) {
                            $productPivot->taxes()->attach($tax->id, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
                        }
                    }
                }
            }
        }
        // Eagerly load products, for future use
        $order->load('products', 'taxes', 'discounts');
        // Return order model, ready for use
        return $order;
    }

    /**
     * Build order copy from model.
     *
     * @param  Model  $order
     * @return stdClass
     *
     * @throws MissingPropertyException
     * @throws \Nikolag\Square\Exceptions\InvalidSquareOrderException
     */
    public function buildOrderCopyFromModel(Model $order)
    {
        try {
            $orderCopy = new stdClass();
            // Create taxes Collection
            $orderCopy->taxes = collect([]);
            if ($order->taxes->isNotEmpty()) {
                $orderCopy->taxes = $this->taxesBuilder->createTaxes($order->taxes->toArray(), $order);
            }
            // Create discounts Collection
            $orderCopy->discounts = collect([]);
            //Order Discounts
            if ($order->discounts->isNotEmpty()) {
                $orderCopy->discounts = $this->discountBuilder->createDiscounts($order->discounts->toArray(), Constants::DEDUCTIBLE_SCOPE_ORDER, $order);
            }
            // Create products Collection
            $orderCopy->products = collect([]);
            //Products
            if ($order->products->isNotEmpty()) {
                foreach ($order->products as $product) {
                    // Create product
                    $productTemp = $this->productBuilder->createProductFromModel($product, $order);
                    // Create initial taxes
                    $productTemp->taxes = collect([]);
                    //Product Taxes
                    if ($product->pivot->taxes->isNotEmpty()) {
                        $productTemp->taxes = $this->taxesBuilder->createTaxes($product->pivot->taxes->toArray(), Constants::DEDUCTIBLE_SCOPE_ORDER, $productTemp->productPivot);

                        // Check for any taxes that are missing on order level
                        $missingTaxes = $productTemp->taxes->reject(function ($tax) use ($orderCopy) {
                            return $orderCopy->taxes->contains($tax);
                        });

                        // Add all missing taxes to order level
                        if ($missingTaxes->isNotEmpty()) {
                            $orderCopy->taxes = $orderCopy->taxes->merge($missingTaxes);
                        }
                    }
                    // Create initial discounts
                    $productTemp->discounts = collect([]);
                    //Product Discounts
                    if ($product->pivot->discounts->isNotEmpty()) {
                        $productTemp->discounts = $this->discountBuilder->createDiscounts($product->pivot->discounts->toArray(), Constants::DEDUCTIBLE_SCOPE_PRODUCT, $productTemp->pivot);

                        // Check for any discounts that are missing on order level
                        $missingDiscounts = $productTemp->discounts->reject(function ($discount) use ($orderCopy) {
                            return $orderCopy->discounts->contains($discount);
                        });

                        // Add all missing discounts to order level
                        if ($missingDiscounts->isNotEmpty()) {
                            $orderCopy->discounts = $orderCopy->discounts->merge($missingDiscounts);
                        }
                    }
                    $orderCopy->products->push($productTemp);
                }
            }

            return $orderCopy;
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required field is missing', 500, $e);
        }
    }

    /**
     * Build order copy from array.
     *
     * @param  array  $order
     * @return stdClass
     *
     * @throws MissingPropertyException
     * @throws \Nikolag\Square\Exceptions\InvalidSquareOrderException
     */
    public function buildOrderCopyFromArray(array $order)
    {
        try {
            $orderCopy = new stdClass();
            // Create taxes Collection
            $orderCopy->taxes = collect([]);
            if (Arr::has($order, 'taxes') && $order['taxes'] != null) {
                $orderCopy->taxes = $this->taxesBuilder->createTaxes($order['taxes']);
            }
            // Create discounts Collection
            $orderCopy->discounts = collect([]);
            //Order Discounts
            if (Arr::has($order, 'discounts') && $order['discounts'] != null) {
                $orderCopy->discounts = $this->discountBuilder->createDiscounts($order['discounts'], Constants::DEDUCTIBLE_SCOPE_ORDER);
            }
            // Create products Collection
            $orderCopy->products = collect([]);
            //Products
            if (Arr::has($order, 'products') && $order['products'] != null) {
                foreach ($order['products'] as $product) {
                    // Create product
                    $productTemp = $this->productBuilder->createProductFromArray($product);
                    // Create discounts Collection
                    $productTemp->discounts = collect([]);
                    //Product Discounts
                    if (Arr::has($product, 'discounts')) {
                        $productTemp->discounts = $this->discountBuilder->createDiscounts($product['discounts'], Constants::DEDUCTIBLE_SCOPE_PRODUCT, $productTemp->productPivot);

                        // Check for any discounts that are missing on order level
                        $missingDiscounts = $productTemp->discounts->reject(function ($discount) use ($orderCopy) {
                            return $orderCopy->discounts->contains($discount);
                        });

                        // Add all missing discounts to order level
                        if ($missingDiscounts->isNotEmpty()) {
                            $orderCopy->discounts = $orderCopy->discounts->merge($missingDiscounts);
                        }
                    }
                    // Create taxes Collection
                    $productTemp->taxes = collect([]);
                    //Product Taxes
                    if (Arr::has($product, 'taxes')) {
                        $productTemp->taxes = $this->taxesBuilder->createTaxes($product['taxes'], $productTemp->productPivot);

                        // Check for any taxes that are missing on order level
                        $missingTaxes = $productTemp->taxes->reject(function ($tax) use ($orderCopy) {
                            return $orderCopy->taxes->contains($tax);
                        });

                        // Add all missing discounts to order level
                        if ($missingTaxes->isNotEmpty()) {
                            $orderCopy->taxes = $orderCopy->taxes->merge($missingTaxes);
                        }
                    }
                    $orderCopy->products->push($productTemp);
                }
            }

            return $orderCopy;
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required field is missing', 500, $e);
        }
    }

    /**
     * Build order model from array.
     *
     * @param  array  $order
     * @param  Model  $emptyModel
     * @return Model
     *
     * @throws MissingPropertyException
     * @throws \Nikolag\Square\Exceptions\InvalidSquareOrderException
     */
    public function buildOrderModelFromArray(array $order, Model $emptyModel)
    {
        $property = config('nikolag.connections.square.order.service_identifier');
        foreach ($order as $key => $value) {
            if ($key
                && $key != 'taxes'
                && $key != 'discounts'
                && $key != 'products'
                && $key != $property
                && $key != 'payment_service_type'
                && $emptyModel->hasAttribute($key)) {
                $emptyModel->{$key} = $value;
            }
        }

        return $emptyModel;
    }
}
