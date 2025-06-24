<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_webhook_events';

    /**
     * Status constants for webhook events.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'square_event_id',
        'event_type',
        'event_data',
        'event_time',
        'status',
        'processed_at',
        'error_message',
        'subscription_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_data' => 'array',
        'event_time' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * The webhook subscription that received this event.
     *
     * @return BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }

    /**
     * Scope a query to only include pending events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending($query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include processed events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeProcessed($query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope a query to only include failed events.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFailed($query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include events of a specific type.
     *
     * @param Builder $query
     * @param string $eventType
     * @return Builder
     */
    public function scopeForEventType($query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Check if this is an order-related event.
     *
     * @return bool
     */
    public function isOrderEvent(): bool
    {
        return str_starts_with($this->event_type, 'order.');
    }

    /**
     * Check if this is a payment-related event.
     *
     * @return bool
     */
    public function isPaymentEvent(): bool
    {
        return str_starts_with($this->event_type, 'payment.');
    }

    /**
     * Get the order ID from the event data.
     *
     * @return string|null
     */
    public function getOrderId(): ?string
    {
        if (!$this->isOrderEvent()) {
            return null;
        }
        $eventTypeKey = match($this->event_type) {
            'order.created' => 'order_created',
            'order.fulfillment.updated' => 'order_fulfillment_updated',
            'order.updated' => 'order_updated',
            default => null,
        };
        return $this->event_data['data']['object'][$eventTypeKey]['order_id'] ?? null;
    }
}
