<?php

namespace Nikolag\Square\Utils;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Models\Product;
use stdClass;

class Util
{
    /**
     * Calculates order total based on orderCopy (stdClass of Model).
     *
     * @param  stdClass  $orderCopy
     * @return float
     */
    public static function calculateTotalOrderCost(stdClass $orderCopy)
    {
        return self::_calculateTotalCost($orderCopy->discounts, $orderCopy->taxes, $orderCopy->products);
    }

    /**
     * Calculate all discounts on order level no matter
     * their scope.
     *
     * @param  Collection  $discounts
     * @param  float  $noDeductiblesCost
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateDiscounts($discounts, float $noDeductiblesCost, $products)
    {
        $totalDiscount = 0;
        if ($discounts->isNotEmpty()) {
            if ($products != null) {
                $totalDiscount = $discounts->map(function ($discount) use ($products, $noDeductiblesCost) {
                    if ((! $discount->pivot && $discount->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) ||
                        ($discount->pivot && $discount->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT)) {
                        return self::_calculateProductDiscounts($products, $discount);
                    } elseif ((! $discount->pivot && $discount->scope === Constants::DEDUCTIBLE_SCOPE_ORDER) ||
                            ($discount->pivot && $discount->pivot->scope === Constants::DEDUCTIBLE_SCOPE_ORDER)) {
                        return self::_calculateOrderDiscounts($discount, $noDeductiblesCost);
                    }

                    return 0;
                })->pipe(function ($total) {
                    return $total->sum();
                });
            }
        }

        return $totalDiscount;
    }

    /**
     * Function which calculates discounts on order level and where percentage
     * takes over precedence over flat amount.
     *
     * @param $discount
     * @param  float  $noDeductiblesCost
     * @return float|int|mixed
     */
    private static function _calculateOrderDiscounts($discount, float $noDeductiblesCost)
    {
        return ($discount->percentage) ? ($noDeductiblesCost * $discount->percentage / 100) :
            $discount->amount;
    }

    /**
     * Function which calculates discounts on product level and where percentage
     * takes over precedence over flat amount.
     *
     * @param $products
     * @param $discount
     * @return float|int|mixed|void
     */
    private static function _calculateProductDiscounts($products, $discount)
    {
        $product = $products->first(function ($product) use ($discount) {
            return $product->pivot->discounts->contains($discount) || $product->discounts->contains($discount);
        });

        if ($product) {
            return ($discount->percentage) ? ($product->price * $product->pivot->quantity * $discount->percentage / 100) :
                $discount->amount;
        }
    }

    /**
     * Function which calculates taxes on product level.
     *
     * @param $products
     * @param $tax
     * @return float|int|void
     */
    private static function _calculateProductTaxes($products, $tax)
    {
        $product = $products->first(function ($product) use ($tax) {
            return $product->pivot->taxes->contains($tax) || $product->taxes->contains($tax);
        });

        if ($product) {
            return $product->price * $product->pivot->quantity * $tax->percentage / 100;
        }
    }

    /**
     * Function which calculates taxes on order level.
     *
     * @param  float  $noDeductiblesCost
     * @param $tax
     * @return float|int
     */
    private static function _calculateOrderTaxes(float $noDeductiblesCost, $tax)
    {
        return $noDeductiblesCost * $tax->percentage / 100;
    }

    /**
     * Calculate all taxes on order level no matter
     * their scope, type of ADDITIVE.
     *
     * @param  Collection  $taxes
     * @param  float  $noDeductiblesCost
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateTaxes($taxes, float $noDeductiblesCost, $products)
    {
        $totalTaxes = 0;
        if ($taxes->isNotEmpty()) {
            if ($products != null) {
                $totalTaxes = $taxes->filter(function ($tax) {
                    return $tax->type === Constants::TAX_ADDITIVE;
                })->map(function ($taxTwo) use ($products, $noDeductiblesCost) {
                    if ((! $taxTwo->pivot && $taxTwo->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) ||
                        ($taxTwo->pivot && $taxTwo->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT)) {
                        return self::_calculateProductTaxes($products, $taxTwo);
                    } elseif ((! $taxTwo->pivot && $taxTwo->scope === Constants::DEDUCTIBLE_SCOPE_ORDER) ||
                        ($taxTwo->pivot && $taxTwo->pivot->scope === Constants::DEDUCTIBLE_SCOPE_ORDER)) {
                        return self::_calculateOrderTaxes($noDeductiblesCost, $taxTwo);
                    }

                    return 0;
                })->pipe(function ($total) {
                    return $total->sum();
                });
            }
        }

        return $totalTaxes;
    }

    /**
     * Calculate total order cost.
     *
     * @param  Collection  $discounts
     * @param  Collection  $taxes
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateTotalCost($discounts, $taxes, $products)
    {
        $noDeductiblesCost = 0;
        $finalCost = 0;
        $lineItemDiscounts = collect([]);
        $lineItemTaxes = collect([]);
        $orderDiscounts = collect([]);
        $orderTaxes = collect([]);

        // Calculate order level discounts scoped with either ORDER or LINE_ITEM
        if ($discounts->isNotEmpty()) {
            $lineItemDiscounts = self::_filterElements(Constants::DEDUCTIBLE_SCOPE_PRODUCT, $discounts);
            $orderDiscounts = self::_filterElements(Constants::DEDUCTIBLE_SCOPE_ORDER, $discounts);
        }

        // Calculate order level taxes scoped with either ORDER or LINE_ITEM
        if ($taxes->isNotEmpty()) {
            $lineItemTaxes = self::_filterElements(Constants::DEDUCTIBLE_SCOPE_PRODUCT, $taxes);
            $orderTaxes = self::_filterElements(Constants::DEDUCTIBLE_SCOPE_ORDER, $taxes);
        }

        // Calculate base total
        if ($products->isNotEmpty()) {
            $finalCost = $noDeductiblesCost = $products->map(function ($product) {
                return $product->price * $product->pivot->quantity;
            })->pipe(function ($total) {
                return $total->sum();
            });
        }

        $finalCost -= self::_calculateDiscounts($lineItemDiscounts->flatten()->merge($orderDiscounts->flatten())->flatten(), $noDeductiblesCost, $products);
        $finalCost -= self::_calculateTaxes($lineItemTaxes->merge($orderTaxes)->flatten(), $finalCost, $products);

        return $finalCost;
    }

    /**
     * Filter elements based on scope and collection of elements.
     *
     * @param  string  $scope  Scope of elements, can be one of: [Constants::DEDUCTIBLE_SCOPE_ORDER, Constants::DEDUCTIBLE_SCOPE_PRODUCT]
     * @param  Collection  $collection  A collection of elements
     */
    private static function _filterElements(string $scope, Collection $collection)
    {
        return $collection->filter(function ($obj) use ($scope) {
            return ($obj->pivot && $obj->pivot->scope === $scope) || $obj->scope === $scope;
        });
    }

    /**
     * Calculates order total based on Model.
     *
     * @param  Model  $order
     * @return float
     */
    public static function calculateTotalOrderCostByModel(Model $order)
    {
        return self::_calculateTotalCost($order->discounts, $order->taxes, $order->products);
    }

    /**
     * Check if source has product.
     *
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $source
     * @param  array|int|Product  $product
     * @return bool
     */
    public static function hasProduct($source, $product)
    {
        // Check if $product is either int, Model or array
        if (is_a($product, Product::class)) {
            return $source->contains($product);
        } elseif (is_array($product)) {
            if (array_key_exists('id', $product)) {
                return $source->contains(Product::find($product['id']));
            } elseif (array_key_exists('name', $product)) {
                return $source->contains(Product::where('name', $product['name'])->first());
            }
        } elseif (is_int($product)) {
            return $source->contains(Product::find($product));
        }

        return false;
    }

    /**
     * Generate random alphanumeric string of supplied length or 30 by default.
     *
     * @param  int  $length
     * @return string
     *
     * @throws \Exception
     */
    public static function uid(int $length = 30)
    {
        return bin2hex(random_bytes($length));
    }
}
