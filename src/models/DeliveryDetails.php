<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Traits\HasRecipient;

class DeliveryDetails extends Model
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
        'recipient_id',
        'carrier',
        'placed_at',
        'deliver_at',
        'prep_time_duration',
        'delivery_window_duration',
        'note',
        'completed_at',
        'in_progress_at',
        'rejected_at',
        'ready_at',
        'delivered_at',
        'canceled_at',
        'cancel_reason',
        'courier_picked_up_at',
        'courier_pickup_window_duration',
        'is_no_contact_delivery',
        'dropoff_notes',
        'courier_provider_name',
        'courier_support_phone_number',
        'square_delivery_id',
        'external_delivery_id',
        'managed_deliver',
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
        'courier_picked_up_at' => 'datetime',
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
}
