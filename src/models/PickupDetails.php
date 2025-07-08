<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Utils\Constants;

class PickupDetails extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_pickup_details';

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
        'expires_at',
        'auto_complete_duration',
        'schedule_type',
        'pickup_at',
        'pickup_window_duration',
        'prep_time_duration',
        'note',
        'cancel_reason',
        'is_curbside_pickup',
        'curbside_pickup_details',
        'schedule_type',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'pickup_at' => 'datetime',
        'placed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ready_at' => 'datetime',
        'expired_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'canceled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'curbside_pickup_details' => 'object',
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
     * Get the fulfillment associated with the pickup.
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
