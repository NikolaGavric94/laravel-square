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
     * @param Model    $order
     * @param stdClass $orderCopy
     *
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
                // Save discount
                $discount->save();
                // If order doesn't have discount, add it
                if (! $order->hasDiscount($discount)) {
                    $order->discounts()->attach($discount->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);
                }
            }
        }

        // Check if order has taxes
        if ($orderCopy->taxes->isNotEmpty()) {
            // For each tax in order
            foreach ($orderCopy->taxes as $tax) {
                // Save tax
                $tax->save();
                // If order doesn't have tax, add it
                if (! $order->hasTax($tax)) {
                    $order->taxes()->attach($tax->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::TAX_NAMESPACE]);
                }
            }
        }

        // Check if order has products
        if ($orderCopy->products->isNotEmpty()) {
            // For each product in order
            foreach ($orderCopy->products as $productClass) {
                // Assign product model
                $product = $productClass->product;
                // If order doesn't have product
                if (! $order->hasProduct($product)) {
                    // Save product
                    $product->save();

                    // Create intermediate table
                    $productPivot = $productClass->productPivot;
                    // Associate product with it
                    $productPivot->product()->associate($product);
                    // Associate order with it
                    $productPivot->order()->associate($order);
                    // Save intermediate model
                    $productPivot->save();

                    // For each discount in product
                    foreach ($productClass->discounts as $discount) {
                        // Save discount
                        $discount->save();
                        // If product doesn't have discount, add it
                        if (! $productPivot->hasDiscount($discount)) {
                            $productPivot->discounts()->attach($discount->id, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);
                        }
                    }

                    // For each tax in product
                    foreach ($productClass->taxes as $tax) {
                        // Save tax
                        $tax->save();
                        // If product doesn't have tax, add it
                        if (! $productPivot->hasTax($tax)) {
                            $productPivot->taxes()->attach($tax->id, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE]);
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
     * @param Model $order
     *
     * @return stdClass
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
                $orderCopy->discounts = $this->discountBuilder->createDiscounts($order->discounts->toArray(), $order);
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
                        $productTemp->taxes = $this->taxesBuilder->createTaxes($product->pivot->taxes->toArray(), $productTemp->productPivot);
                    }
                    // Create initial discounts
                    $productTemp->discounts = collect([]);
                    //Product Discounts
                    if ($product->pivot->discounts->isNotEmpty()) {
                        $productTemp->discounts = $this->discountBuilder->createDiscounts($product->pivot->discounts->toArray(), $productTemp->productPivot);
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
     * @param array $order
     *
     * @return stdClass
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
                $orderCopy->discounts = $this->discountBuilder->createDiscounts($order['discounts']);
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
                        $productTemp->discounts = $this->discountBuilder->createDiscounts($product['discounts'], $productTemp->productPivot);
                    }
                    // Create taxes Collection
                    $productTemp->taxes = collect([]);
                    //Product Taxes
                    if (Arr::has($product, 'taxes')) {
                        $productTemp->taxes = $this->taxesBuilder->createTaxes($product['taxes'], $productTemp->productPivot);
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
     * @param array $order
     * @param Model $emptyModel
     *
     * @return Model
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
