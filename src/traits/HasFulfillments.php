<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nikolag\Square\Utils\Constants;

trait HasFulfillments
{
    /**
     * Does an order have a product.
     *
     * @param  mixed  $product
     * @return bool
     */
    public function hasFulfillment(mixed $fulfillment): bool
    {
        $val = is_array($fulfillment)
            ? (array_key_exists('id', $fulfillment) ? Product::find($fulfillment['id']) : $fulfillment )
            : $fulfillment;

        return $this->fulfillments()->get()->contains($val);
    }

    /**
     * Return a list of fulfillments which are associated with order.
     *e
     * @return BelongsToMany
     */
    public function fulfillments(): BelongsToMany
    {
        return $this->belongsToMany(
            Constants::FULFILLMENT_NAMESPACE,
            'nikolag_fulfillment_order',
            'order_id',
            'fulfillment_id'
        )->using(Constants::ORDER_PRODUCT_NAMESPACE);
    }

}
