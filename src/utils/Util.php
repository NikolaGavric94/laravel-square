<?php

namespace Nikolag\Square\Utils;

use Illuminate\Support\Collection;
use stdClass;

class Util
{
    /**
     * Calculates order total.
     *
     * @param stdClass $orderCopy
     *
     * @return float
     */
    public static function calculateTotalOrderCost(stdClass $orderCopy)
    {
        $noDeductiblesCost = 0;
        // Calculate gross amount (total)
        foreach ($orderCopy->products as $product) {
            $productPivot = $product->productPivot;
            $product = $product->product;

            $totalPrice = $productPivot->quantity * $product->price;
            $currentPrice = $totalPrice;
            $noDeductiblesCost += $currentPrice;
        }
        // Apply discounts on order and product level
        $currentOrderPrice = $noDeductiblesCost;
        // Order level discounts
        foreach ($orderCopy->discounts as $orderDiscount) {
            //Product level discounts
            foreach ($orderCopy->products as $currProduct) {
                $productPivot = $currProduct->productPivot;
                $product = $currProduct->product;

                $totalProductPrice = $productPivot->quantity * $product->price;
                $currentProductPrice = $totalProductPrice;

                // Calculate product discounts
                foreach ($currProduct->discounts as $discount) {
                    if ($discount->amount && ! $discount->percentage) {
                        $noDeductiblesCost -= $discount->amount;
                        $currentProductPrice -= $discount->amount;
                    }
                    if ($discount->percentage && ! $discount->amount) {
                        $noDeductiblesCost -= $totalProductPrice * $discount->percentage / 100;
                        $currentProductPrice -= $totalProductPrice * $discount->percentage / 100;
                    }
                }
                //Algorithm based off of https://docs.connect.squareup.com/articles/orders-api-overview
                $discountAmount = ($orderDiscount->percentage) ? $currentOrderPrice * $orderDiscount->percentage / 100 : $orderDiscount->amount;
                $noDeductiblesCost -= $discountAmount;
            }
        }

        // Order level taxes
        foreach ($orderCopy->taxes as $orderTax) {
            //Product level taxes
            foreach ($orderCopy->products as $currProduct) {
                $productPivot = $currProduct->productPivot;
                $product = $currProduct->product;

                $totalProductPrice = $productPivot->quantity * $product->price;
                $currentProductPrice = $totalProductPrice;

                // Calculate product discounts
                foreach ($currProduct->discounts as $discount) {
                    if ($discount->amount && ! $discount->percentage) {
                        $currentProductPrice -= $discount->amount;
                    }
                    if ($discount->percentage && ! $discount->amount) {
                        $currentProductPrice -= $totalProductPrice * $discount->percentage / 100;
                    }
                }

                // Calculate product taxes
                foreach ($currProduct->taxes as $tax) {
                    if ($tax->type === Constants::TAX_ADDITIVE) {
                        $noDeductiblesCost += $currentProductPrice * $tax->percentage / 100;
                    }
                }
                // Calculate order taxes
                if ($orderTax->type === Constants::TAX_ADDITIVE) {
                    $taxAmount = $currentOrderPrice * $orderTax->percentage / 100;
                    $noDeductiblesCost += $taxAmount;
                }
            }
        }

        return $noDeductiblesCost;
    }

    /**
     * Check if source has product.
     *
     * @param Collection $source
     * @param mixed      $product
     *
     * @return bool
     */
    public static function hasProduct(Collection $source, $product)
    {
        // Product is not found
        $found = false;
        // Go through all products
        foreach ($source as $curr) {
            if ($curr->product == $product) {
                // product matches
                $found = true;
                // to stop iterating
                break;
            }
        }

        return $found;
    }

    /**
     * Generate random alphanumeric string of supplied length or 30 by default.
     *
     * @param int $length
     */
    public static function uid(int $length = 30)
    {
        return bin2hex(random_bytes($length));
    }
}
