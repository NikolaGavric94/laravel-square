<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Traits\HasRecipient;

class ShipmentDetails extends Model
{
    /**
     * Traits
     */
    use HasRecipient;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_shipment_details';

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
        'recipient_id',
        'carrier',
        'shipping_note',
        'shipping_type',
        'tracking_number',
        'tracking_url',
        'placed_at',
        'in_progress_at',
        'packaged_at',
        'expected_shipped_at',
        'shipped_at',
        'canceled_at',
        'cancel_reason',
        'failed_at',
        'failure_reason',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'placed_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'packaged_at' => 'datetime',
        'expected_shipped_at' => 'datetime',
        'shipped_at' => 'datetime',
        'canceled_at' => 'datetime',
        'failed_at' => 'datetime',
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
     * Get the fulfillment associated with the shipment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function fulfillment()
    {
        return $this->morphOne(
            Constants::FULFILLMENT_NAMESPACE,
            'fulfillmentDetails'
        );
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d\TH:i:sP');
    }
}
