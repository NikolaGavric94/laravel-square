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
     * Retrieve recipient if they exist, otherwise return false.
     *
     * @param  mixed  $recipient   The recipient to search for.
     *
     * @return bool
     */
    public function hasRecipient(mixed $recipient): mixed
    {
        if (is_array($recipient) && array_key_exists('id', $recipient)) {
            $val = Recipient::find($recipient['id']);
        } else {
            $val = $recipient;
        }

        return $this->products()->get()->contains($val);
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
