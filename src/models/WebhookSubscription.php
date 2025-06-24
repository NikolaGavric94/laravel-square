<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookSubscription extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_webhook_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'square_id',
        'name',
        'notification_url',
        'event_types',
        'api_version',
        'signature_key',
        'is_enabled',
        'is_active',
        'last_tested_at',
        'last_failed_at',
        'last_error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_types' => 'array',
        'is_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_failed_at' => 'datetime',
    ];

    /**
     * Get the webhook events for this subscription.
     *
     * @return HasMany
     */
    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'webhook_subscription_id');
    }

    /**
     * Scope a query to only include enabled webhooks.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeEnabled($query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope a query to only include webhooks for a specific event type.
     *
     * @param Builder $query
     * @param string $eventType
     * @return Builder
     */
    public function scopeForEventType($query, string $eventType): Builder
    {
        return $query->whereJsonContains('event_types', $eventType);
    }

    /**
     * Check if this subscription handles a specific event type.
     *
     * @param string $eventType
     * @return bool
     */
    public function handlesEventType(string $eventType): bool
    {
        return in_array($eventType, $this->event_types ?? []);
    }

    /**
     * Scope a query to only include active webhooks.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Mark the webhook subscription as tested.
     *
     * @return bool
     */
    public function markAsTested(): bool
    {
        return $this->update([
            'last_tested_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the webhook subscription as failed with an error.
     *
     * @param string $error
     * @return bool
     */
    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'last_failed_at' => now(),
            'last_error' => $error,
        ]);
    }

    /**
     * Check if the webhook subscription is enabled and active.
     *
     * @return bool
     */
    public function isOperational(): bool
    {
        return $this->is_enabled && $this->is_active;
    }
}
