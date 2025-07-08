<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Utils\Constants;

trait HasFulfillments
{
    /**
     * Checks if this model already has a specific fulfillment.
     *
     * @param mixed $fulfillment The fulfillment to check for.
     * @return boolean
     */
    public function hasFulfillment(mixed $fulfillment): bool
    {
        $val = is_array($fulfillment)
            ? (array_key_exists('id', $fulfillment) ? Fulfillment::find($fulfillment['id']) : $fulfillment)
            : $fulfillment;

        return $this->fulfillments()->get()->contains($val);
    }

    /**
     * Return the fulfillments associated with this model.
     *
     * @return HasMany
     */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Constants::FULFILLMENT_NAMESPACE);
    }
}
