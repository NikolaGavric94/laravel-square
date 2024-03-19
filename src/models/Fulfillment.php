<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Utils\Constants;

class Fulfillment extends Model
{
    /**
     * Return a list of orders in which this product is included.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function orders()
    {
        return $this->belongsTo(
            config('nikolag.connections.square.order.namespace'),
            'nikolag_fulfillment_order',
            'fulfillment_id',
            'order_id'
        )->using(Constants::ORDER_FULFILLMENT_NAMESPACE);
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
