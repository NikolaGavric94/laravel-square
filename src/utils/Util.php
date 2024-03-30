<?php

namespace Nikolag\Square\Utils;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Fulfillment;
use stdClass;

class Util
{
    /**
     * Calculates order total based on orderCopy (stdClass of Model).
     *
     * @param  stdClass  $orderCopy
     * @return float|int
     */
    public static function calculateTotalOrderCost(stdClass $orderCopy): float|int
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
    private static function _calculateDiscounts(Collection $discounts, float $noDeductiblesCost, Collection $products): float|int
    {
        $totalDiscount = 0;
        if ($discounts->isNotEmpty() && $products->isNotEmpty()) {
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

        return $totalDiscount;
    }

    /**
     * Function which calculates discounts on order level and where percentage
     * takes over precedence over flat amount.
     *
     * @param  $discount
     * @param  float  $noDeductiblesCost
     * @return float|int
     */
    private static function _calculateOrderDiscounts($discount, float $noDeductiblesCost): float|int
    {
        return ($discount->percentage) ? ($noDeductiblesCost * $discount->percentage / 100) :
            $discount->amount;
    }

    /**
     * Function which calculates discounts on product level and where percentage
     * takes over precedence over flat amount.
     *
     * @param  $products
     * @param  $discount
     * @return float|int
     */
    private static function _calculateProductDiscounts($products, $discount): float|int
    {
        $product = $products->first(function ($product) use ($discount) {
            return $product->pivot->discounts->contains($discount) || $product->discounts->contains($discount);
        });

        if ($product) {
            return ($discount->percentage) ? ($product->price * $product->pivot->quantity * $discount->percentage / 100) :
                $discount->amount;
        } else {
            return 0;
        }
    }

    /**
     * Function which calculates taxes on product level.
     *
     * @param  $products
     * @param  $tax
     * @return float|int
     */
    private static function _calculateProductTaxes($products, $tax): float|int
    {
        $product = $products->first(function ($product) use ($tax) {
            return $product->pivot->taxes->contains($tax) || $product->taxes->contains($tax);
        });

        if ($product) {
            return $product->price * $product->pivot->quantity * $tax->percentage / 100;
        } else {
            return 0;
        }
    }

    /**
     * Function which calculates taxes on order level.
     *
     * @param  float  $noDeductiblesCost
     * @param  $tax
     * @return float|int
     */
    private static function _calculateOrderTaxes(float $noDeductiblesCost, $tax): float|int
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
    private static function _calculateTaxes(Collection $taxes, float $noDeductiblesCost, Collection $products): float|int
    {
        $totalTaxes = 0;
        if ($taxes->isNotEmpty() && $products->isNotEmpty()) {
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
    private static function _calculateTotalCost(Collection $discounts, Collection $taxes, Collection $products): float|int
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
    private static function _filterElements(string $scope, Collection $collection): Collection
    {
        return $collection->filter(function ($obj) use ($scope) {
            return ($obj->pivot && $obj->pivot->scope === $scope) || $obj->scope === $scope;
        });
    }

    /**
     * Calculates order total based on Model.
     *
     * @param  Model  $order
     * @return float|int
     */
    public static function calculateTotalOrderCostByModel(Model $order): float|int
    {
        return self::_calculateTotalCost($order->discounts, $order->taxes, $order->products);
    }

    /**
     * Check if source has fulfillment.
     *
     * @param  stdClass  $order
     * @return bool
     */
    public static function hasFulfillment(
        \Illuminate\Database\Eloquent\Collection|Collection $source,
        Fulfillment|int|array|null $fulfillment
    ): bool {
        // Check if $fulfillment is either int, Model or array
        if (is_a($fulfillment, Fulfillment::class)) {
            return $source->contains($fulfillment);
        } elseif (is_array($fulfillment)) {
            if (array_key_exists('id', $fulfillment)) {
                return $source->contains(Fulfillment::find($fulfillment['id']));
            } elseif (array_key_exists('name', $fulfillment)) {
                return $source->contains(Fulfillment::where('name', $fulfillment['name'])->first());
            }
        } elseif (is_int($fulfillment)) {
            return $source->contains(Fulfillment::find($fulfillment));
        }

        return false;
    }

    /**
     * Check if source has product.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|Collection  $source
     * @param  int|array|Product|null  $product
     * @return bool
     */
    public static function hasProduct(\Illuminate\Database\Eloquent\Collection|Collection $source, Product|int|array|null $product): bool
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
    public static function uid(int $length = 30): string
    {
        return bin2hex(random_bytes($length));
    }
}
