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
     * Get the object type key for the event data based on event type.
     *
     * Different Square webhook event types store their data under different keys
     * in the event_data['data']['object'] structure. This method maps event types
     * to their corresponding object keys.
     *
     * @return string|null The object key for this event type, or null if unknown
     */
    public function getObjectTypeKey(): ?string
    {
        return match($this->event_type) {
            'order.created' => 'order_created',
            'order.fulfillment.updated' => 'order_fulfillment_updated',
            'order.updated' => 'order_updated',
            'payment.created' => 'payment',
            'payment.updated' => 'payment',
            default => null,
        };
    }

    /**
     * Get the order ID from the event data.
     *
     * @return string|null
     */
    public function getOrderId(): ?string
    {
        $eventObject = $this->getEventObject();
        return $eventObject[$this->getObjectTypeKey()]['order_id'] ?? null;
    }

    /**
     * Get the payment ID from the event data.
     *
     * @return string|null
     */
    public function getPaymentId(): ?string
    {
        $eventObject = $this->getEventObject();
        return $eventObject['payment']['id'] ?? null;
    }

    /**
     * Get the merchant ID from the event data.
     *
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->event_data['merchant_id'] ?? null;
    }

    /**
     * Get the location ID from the event data.
     *
     * @return string|null
     */
    public function getLocationId(): ?string
    {
        $eventObject = $this->getEventObject();
        return $eventObject[$this->getObjectTypeKey()]['location_id'] ?? null;
    }

    /**
     * Mark the event as processed.
     *
     * @return bool
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark the event as failed with an error message.
     *
     * @param string $errorMessage
     * @return bool
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if the event is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the event has been processed.
     *
     * @return bool
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the event processing failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get the event data object for easy access.
     *
     * @return array|null
     */
    public function getEventObject(): ?array
    {
        return $this->event_data['data']['object'] ?? null;
    }

}
