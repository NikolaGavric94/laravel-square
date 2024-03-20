<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Fulfillment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_fulfillments';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'state',
        'uid',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Return the order associated with this fulfillment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(
            config('nikolag.connections.square.order.namespace'),
            'nikolag_fulfillment_order',
            'id',
            'order_id'
        );
    }

    /**
     * Returns the fulfillment details associated with this fulfillment.  The three associated models are:
     * - \Nikolag\Square\Models\PickupDetails
     * - \Nikolag\Square\Models\ShipmentDetails
     * - \Nikolag\Square\Models\ShipmentDetails
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function fulfillmentDetails()
    {
        return $this->morphTo();
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
