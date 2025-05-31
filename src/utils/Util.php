<?php

namespace Nikolag\Square\Utils;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Product;
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
        $allServiceCharges = self::collectServiceCharges($orderCopy);
        return self::_calculateTotalCost($orderCopy->discounts, $orderCopy->taxes, $allServiceCharges, $orderCopy->products);
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
        if ($discounts->isEmpty() || $products->isEmpty()) {
            return 0;
        }

        return $discounts->sum(function ($discount) use ($products, $noDeductiblesCost) {
            $scope = $discount->pivot ? $discount->pivot->scope : $discount->scope;

            return match ($scope) {
                Constants::DEDUCTIBLE_SCOPE_PRODUCT => self::_calculateProductDiscounts($products, $discount),
                Constants::DEDUCTIBLE_SCOPE_ORDER => self::_calculateOrderDiscounts($discount, $noDeductiblesCost),
                default => 0
            };
        });
    }

    /**
     * Function which calculates the net price by removing any additive taxes to the entire order.
     *
     * @param  float  $discountCount
     * @param  Collection  $inclusiveTaxes
     * @return float|int
     */
    private static function _calculateNetPrice(float $discountCost, Collection $inclusiveTaxes): float|int
    {
        // Get all the inclusive taxes
        $inclusiveTaxPercent = $inclusiveTaxes->filter(function ($tax) {
            return $tax->type === Constants::TAX_INCLUSIVE;
        })->map(function ($tax) {
            return $tax->percentage;
        })->pipe(function ($total) {
            return $total->sum();
        }) / 100;

        // Calculate the net price (amount without inclusive tax)
        $netPrice = $discountCost / (1 + $inclusiveTaxPercent);

        return $netPrice;
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
            return ($discount->percentage) ? ($product->pivot->price_money_amount * $product->pivot->quantity * $discount->percentage / 100):
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
     * @param  Collection  $inclusiveTaxes
     * @param  Collection  $discounts
     * @return float|int
     */
    private static function _calculateProductTaxes($products, $tax, Collection $inclusiveTaxes, Collection $discounts): float|int
    {
        $product = $products->first(function ($product) use ($tax) {
            return $product->pivot->taxes->contains($tax) || $product->taxes->contains($tax);
        });

        if ($product) {
            // Get the total product cost (price * quantity)
            $totalCost = $product->pivot->price_money_amount * $product->pivot->quantity;

            // Calculate order discounts as this will impact the taxes calculated
            $discountCost = $totalCost - self::_calculateDiscounts($discounts, $totalCost, $products);

            $netPrice = self::_calculateNetPrice($discountCost, $inclusiveTaxes);

            // Calculate and round the product taxes
            $productTaxes = $netPrice * ($tax->percentage / 100);

            return round($productTaxes);
        } else {
            return 0;
        }
    }

    /**
     * Function which calculates taxes on order level.
     *
     * @param  float  $discountCost
     * @param  $tax
     * @param  Collection  $inclusiveTaxes
     * @return float|int
     */
    private static function _calculateOrderTaxes(float $discountCost, $tax, Collection $inclusiveTaxes): float|int
    {
        // Calculate the net price (amount without inclusive tax)
        $netPrice = self::_calculateNetPrice($discountCost, $inclusiveTaxes);

        // Get the order taxes
        $orderTaxes = $netPrice * $tax->percentage / 100;

        return round($orderTaxes);
    }

    /**
     * Function which calculates service charges on order level and where percentage
     * takes over precedence over flat amount.
     *
     * @param  $serviceCharge
     * @param  float  $amount
     * @return float|int
     */
    private static function _calculateOrderServiceCharges($serviceCharge, float $amount): float|int
    {
        return ($serviceCharge->percentage) ? round($amount * $serviceCharge->percentage / 100) :
            $serviceCharge->amount_money;
    }

    /**
     * Function which calculates service charges on product level and where percentage
     * takes over precedence over flat amount.
     *
     * @param  $products
     * @param  $serviceCharge
     * @return float|int
     */
    private static function _calculateProductServiceCharges($products, $serviceCharge): float|int
    {
        // Handle apportioned service charges efficiently
        if ($serviceCharge->calculation_phase === Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL) {
            throw new Exception('Service charge calculation phase "SUBTOTAL" cannot be applied to products in an order.');
        }

        if ($serviceCharge->calculation_phase === Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT) {
            // Apply fixed amount per line item quantity
            $totalQuantity = $products->sum('pivot.quantity');
            return $serviceCharge->amount_money * $totalQuantity;
        }

        if ($serviceCharge->calculation_phase === Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE) {
            // Apply percentage to total product value - use cached calculation if available
            $totalValue = $products->sum(function ($product) {
                return $product->pivot->price_money_amount * $product->pivot->quantity;
            });
            return $totalValue * $serviceCharge->percentage / 100;
        }

        // For non-apportioned service charges, find the specific product efficiently
        $targetProduct = $products->first(function ($product) use ($serviceCharge) {
            return $product->pivot->serviceCharges->contains($serviceCharge);
        });

        if (!$targetProduct) {
            return 0;
        }

        $pivot = $targetProduct->pivot;
        return $serviceCharge->percentage ?
            ($pivot->price_money_amount * $pivot->quantity * $serviceCharge->percentage / 100) :
            $serviceCharge->amount_money;
    }

    /**
     * Calculate all service charges on order level no matter
     * their scope.
     *
     * @param  Collection  $serviceCharges
     * @param  float  $baseAmount
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateServiceCharges(Collection $serviceCharges, float $baseAmount, Collection $products): float|int
    {
        if ($serviceCharges->isEmpty() || $products->isEmpty()) {
            return 0;
        }

        return $serviceCharges->sum(function ($serviceCharge) use ($products, $baseAmount) {
            $scope = $serviceCharge->pivot ? $serviceCharge->pivot->scope : $serviceCharge->scope;

            return match ($scope) {
                Constants::DEDUCTIBLE_SCOPE_PRODUCT => self::_calculateProductServiceCharges($products, $serviceCharge),
                Constants::DEDUCTIBLE_SCOPE_ORDER => self::_calculateOrderServiceCharges($serviceCharge, $baseAmount),
                default => 0
            };
        });
    }

    /**
     * Calculate taxes on service charges based on their treatment type.
     *
     * @param  Collection  $serviceCharges
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateServiceChargeTaxes(Collection $serviceCharges, Collection $products): float|int
    {
        if ($serviceCharges->isEmpty()) {
            return 0;
        }

        return $serviceCharges->sum(function ($serviceCharge) use ($products) {
            // Apportioned service charges inherit taxes from line items - no direct taxes
            if (
                $serviceCharge->treatment_type === Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED
                || $serviceCharge->taxable === false
            ) {
                return 0;
            }

            // Skip if no taxes are associated with this service charge
            $serviceChargeTaxes = $serviceCharge->taxes ?? collect([]);
            if ($serviceChargeTaxes->isEmpty()) {
                return 0;
            }

            // Calculate the service charge amount efficiently
            $scope = $serviceCharge->pivot ? $serviceCharge->pivot->scope : $serviceCharge->scope;
            $serviceChargeAmount = match ($scope) {
                Constants::DEDUCTIBLE_SCOPE_PRODUCT => self::_calculateProductServiceCharges($products, $serviceCharge),
                Constants::DEDUCTIBLE_SCOPE_ORDER => $serviceCharge->percentage ?
                    round($serviceCharge->percentage / 100 * self::_getOrderBaseAmount($products)) :
                    $serviceCharge->amount_money,
                default => 0
            };

            // Apply taxes to the service charge amount
            return $serviceChargeTaxes->sum(function ($tax) use ($serviceChargeAmount) {
                return round($serviceChargeAmount * $tax->percentage / 100);
            });
        });
    }

    /**
     * Collects all the service charges from products and order and combines them.
     *
     * @param Model|stdClass $order
     *
     * @return Collection
     */
    public static function collectServiceCharges(Model|stdClass $order): Collection
    {
        // Collect service charges from order level (with taxes)
        $orderServiceCharges = $order instanceof Model
            ? $order->serviceCharges()->with('taxes')->get() ?? collect([])
            : $order->serviceCharges ?? collect([]);

        // Collect service charges from product pivots (with taxes)
        $productServiceCharges = collect([]);
        if ($order->products && $order->products->isNotEmpty()) {
            $productServiceCharges = $order->products->flatMap(function ($product) {
                return $product instanceof Model
                    ? $product->pivot->serviceCharges()->with('taxes')->get() ?? collect([])
                    : $product->pivot->serviceCharges ?? collect([]);
            });
        }

        // Merge all service charges
        return $orderServiceCharges->merge($productServiceCharges);
    }

    /**
     * Get the base order amount for service charge calculations.
     *
     * @param  Collection  $products
     * @return float|int
     */
    private static function _getOrderBaseAmount(Collection $products): float|int
    {
        return $products->sum(function ($product) {
            $pivot = $product->pivot;
            $productPrice = $pivot->price_money_amount;

            // Add modifier costs efficiently
            if ($pivot->modifiers->isNotEmpty()) {
                $productPrice += $pivot->modifiers->sum(function ($modifier) {
                    return $modifier->modifiable?->price_money_amount ?? 0;
                });
            }

            return $productPrice * $pivot->quantity;
        });
    }

    /**
     * Calculate all additive taxes on order level.
     * Inclusive taxes are not added to the cost as they're already included in the price.
     *
     * @param  Collection  $taxes
     * @param  float  $discountCost
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateAdditiveTaxes(Collection $taxes, float $discountCost, Collection $products, Collection $discounts): float|int
    {
        if ($taxes->isEmpty() || $products->isEmpty()) {
            return 0;
        }

        // Pre-filter taxes for efficiency
        $additiveTaxes = $taxes->filter(fn($tax) => $tax->type === Constants::TAX_ADDITIVE);
        $inclusiveTaxes = $taxes->filter(fn($tax) => $tax->type === Constants::TAX_INCLUSIVE);

        if ($additiveTaxes->isEmpty()) {
            return 0;
        }

        return $additiveTaxes->sum(function ($tax) use ($products, $discountCost, $discounts, $inclusiveTaxes) {
            $scope = $tax->pivot ? $tax->pivot->scope : $tax->scope;

            return match ($scope) {
                Constants::DEDUCTIBLE_SCOPE_PRODUCT => self::_calculateProductTaxes($products, $tax, $inclusiveTaxes, $discounts),
                Constants::DEDUCTIBLE_SCOPE_ORDER => self::_calculateOrderTaxes($discountCost, $tax, $inclusiveTaxes),
                default => 0
            };
        });
    }

    /**
     * Calculate total order cost.
     *
     * @param  Collection  $discounts
     * @param  Collection  $taxes
     * @param  Collection  $serviceCharges
     * @param  Collection  $products
     * @return float|int
     */
    private static function _calculateTotalCost(Collection $discounts, Collection $taxes, Collection $serviceCharges, Collection $products): float|int
    {
        // Early validation
        if ($products->isEmpty()) {
            throw new Exception('Total cost cannot be calculated without products.');
        }

        // Pre-filter all collections by scope once for efficiency
        $allDiscounts = self::_mergeCollectionsByScope($discounts);
        $allTaxes = self::_mergeCollectionsByScope($taxes);
        $allServiceCharges = self::_mergeCollectionsByScope($serviceCharges);

        // Separate service charges by calculation phase
        $subtotalServiceCharges = $allServiceCharges->filter(function ($serviceCharge) {
            return in_array($serviceCharge->calculation_phase, [
                    Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL,
                    Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT,
                    Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE
            ]);
        });

        $totalServiceCharges = $allServiceCharges->filter(function ($serviceCharge) {
            return $serviceCharge->calculation_phase === Constants::SERVICE_CHARGE_CALCULATION_PHASE_TOTAL;
        });

        // Cache product calculations - calculate base cost only once
        $productCalculations = self::_calculateProductTotals($products);
        $noDeductiblesCost = $productCalculations['baseCost'];

        // Apply discounts first to the subtotal
        $discountCost = $noDeductiblesCost - self::_calculateDiscounts($allDiscounts, $noDeductiblesCost, $products);

        // Add subtotal-phase service charges to discount cost
        $subTotalAmount = $discountCost + self::_calculateServiceCharges($subtotalServiceCharges, $discountCost, $products);

        // Apply taxes to the cost including service charges
        $taxedCost = $subTotalAmount + self::_calculateAdditiveTaxes($allTaxes, $subTotalAmount, $products, $allDiscounts);

        // Add total-phase service charges after taxes
        $totalServiceChargeAmount = self::_calculateServiceCharges($totalServiceCharges, $taxedCost, $products);

        // Finally, calculate service charge taxes
        $serviceChargeTaxAmount = self::_calculateServiceChargeTaxes($allServiceCharges, $products);

        return $taxedCost + $totalServiceChargeAmount + $serviceChargeTaxAmount;
    }

    /**
     * Efficiently merge collections by scope to avoid multiple filter operations.
     *
     * @param Collection $collection
     * @return Collection
     */
    private static function _mergeCollectionsByScope(Collection $collection): Collection
    {
        if ($collection->isEmpty()) {
            return collect([]);
        }

        return $collection->filter(function ($obj) {
            $scope = $obj->pivot ? $obj->pivot->scope : $obj->scope;
            return in_array($scope, [Constants::DEDUCTIBLE_SCOPE_ORDER, Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
        });
    }

    /**
     * Calculate product totals once and cache for reuse.
     *
     * @param Collection $products
     * @return array
     */
    private static function _calculateProductTotals(Collection $products): array
    {
        $baseCost = 0;
        $productDetails = [];

        foreach ($products as $product) {
            $pivot = $product->pivot;
            $productPrice = $pivot->price_money_amount;

            // Calculate modifier cost once
            $modifierCost = 0;
            if ($pivot->modifiers->isNotEmpty()) {
                $modifierCost = $pivot->modifiers->sum(function ($modifier) {
                    return $modifier->modifiable?->price_money_amount ?? 0;
                });
            }

            $totalProductPrice = $productPrice + $modifierCost;
            $lineTotal = $totalProductPrice * $pivot->quantity;

            $baseCost += $lineTotal;
            $productDetails[] = [
                'product' => $product,
                'basePrice' => $productPrice,
                'modifierCost' => $modifierCost,
                'totalPrice' => $totalProductPrice,
                'quantity' => $pivot->quantity,
                'lineTotal' => $lineTotal
            ];
        }

        return [
            'baseCost' => $baseCost,
            'productDetails' => $productDetails
        ];
    }

    /**
     * Calculates order total based on Model.
     *
     * @param  Model  $order
     * @return float|int
     */
    public static function calculateTotalOrderCostByModel(Model $order): float|int
    {
        $allServiceCharges = self::collectServiceCharges($order);
        return self::_calculateTotalCost($order->discounts, $order->taxes, $allServiceCharges, $order->products);
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
