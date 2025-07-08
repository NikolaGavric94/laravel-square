<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Utils\Constants;

class DeliveryDetails extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_delivery_details';

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
        'fulfillment_id',
        'schedule_type',
        'prep_time_duration',
        'delivery_window_duration',
        'note',
        'canceled_at',
        'cancel_reason',
        'courier_pickup_window_duration',
        'is_no_contact_delivery',
        'dropoff_notes',
        'courier_provider_name',
        'courier_support_phone_number',
        'managed_delivery',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'placed_at' => 'datetime',
        'deliver_at' => 'datetime',
        'completed_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime',
        'canceled_at' => 'datetime',
        'courier_pickup_at' => 'datetime',
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
     * Get the fulfillment associated with the delivery.
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
     * @param \DateTimeInterface $date The date to serialize.
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format(Constants::DATE_FORMAT);
    }
}
