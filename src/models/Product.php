<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Nikolag\Core\Models\Product as CoreProduct;
use Nikolag\Square\Utils\Constants;

class Product extends CoreProduct
{
    /**
     * Return a list of orders in which this product is included.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function orders()
    {
        return $this->belongsToMany(config('nikolag.connections.square.order.namespace'), 'nikolag_product_order', 'product_id', 'order_id')->using(Constants::ORDER_PRODUCT_NAMESPACE)->withPivot('quantity', 'id');
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
