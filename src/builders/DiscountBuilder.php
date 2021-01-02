<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Discount;

class DiscountBuilder
{
    /**
     * Find or create discount models
     * from discounts array.
     *
     * @param array $discounts
     * @param Model $parent
     *
     * @return \Illuminate\Support\Collection
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function createDiscounts(array $discounts, Model $parent = null)
    {
        $temp = collect([]);
        foreach ($discounts as $discount) {
            //If discount doesn't have amount AND percentage in discounts table
            //throw new exception because it should have at least 1
            if ((! isset($discount['amount']) || $discount['amount'] == null || $discount['amount'] == 0) &&
                (! isset($discount['percentage']) || $discount['percentage'] == null || $discount['percentage'] == '')) {
                throw new MissingPropertyException('Both $amount and $percentage property for object Discount are missing, 1 is required', 500);
            }
            //If discount have amount AND percentage in discount table
            //throw new exception because it should only 1
            if ((isset($discount['amount']) && ($discount['amount'] != null || $discount['amount'] != 0)) &&
                (isset($discount['percentage']) && ($discount['percentage'] != null || $discount['percentage'] != 0.0))
            ) {
                throw new InvalidSquareOrderException('Both $amount and $percentage exist for object Discount, only 1 is allowed', 500);
            }
            //Check if parent is present or parent already has this discount or if discount
            //doesn't have property $id then create new Discount object
            if (($parent && ! $parent->hasDiscount($discount)) || ! Arr::has($discount, 'id')) {
                $tempDiscount = new Discount($discount);
            } else {
                $tempDiscount = Discount::find($discount['id']);
            }
            $temp->push($tempDiscount);
        }

        return $temp;
    }
}
