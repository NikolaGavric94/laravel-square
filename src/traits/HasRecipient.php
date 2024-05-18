<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikolag\Square\Utils\Constants;

trait HasRecipient
{
    /**
     * Retrieve merchant recipients.
     *
     * @return BelongsTo
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            Constants::RECIPIENT_NAMESPACE,
            'recipient_id',
            'id',
        );
    }

    /**
     * Retrieve recipient if he exists, otherwise return false.
     *
     * @param  string  $key   The column to search by.
     * @param  string  $value The value to search for.
     *
     * @return mixed
     */
    public function hasRecipient(string $key, string $value): mixed
    {
        $query = $this->recipients()->where($key, $value);

        return $query->exists() ?
            $query->first() : false;
    }

    /**
     * All fulfillments.
     *
     * @return HasMany
     */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(
            Constants::FULFILLMENT_NAMESPACE,
            'recipient_id',
            config('nikolag.connections.square.user.identifier')
        );
    }
}
