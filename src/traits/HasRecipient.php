<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Utils\Constants;

trait HasRecipient
{
    /**
     * Retrieve recipients related to this model.
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
     * @param mixed $recipient The recipient to search for.
     * @return boolean
     */
    public function hasRecipient(mixed $recipient): bool
    {
        if (is_array($recipient) && array_key_exists('id', $recipient)) {
            $val = Recipient::find($recipient['id']);
        } else {
            $val = $recipient;
        }

        return $this->recipient && $this->recipient->is($val);
    }

    /**
     * All fulfillments associated with this recipient.
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
