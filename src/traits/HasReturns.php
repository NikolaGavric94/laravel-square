<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikolag\Square\Models\OrderReturn;
use Nikolag\Square\Utils\Constants;

trait HasReturns
{
    /**
     * Checks if this model already has a specific return.
     *
     * @param  mixed  $return
     * @return bool
     */
    public function hasReturn(mixed $return): bool
    {
        $val = is_array($return)
            ? (array_key_exists('id', $return) ? OrderReturn::find($return['id']) : $return)
            : $return;

        return $this->returns()->get()->contains($val);
    }

    /**
     * Return the returns associated with this model.
     *
     * @return HasMany
     */
    public function returns(): HasMany
    {
        return $this->hasMany(Constants::ORDER_RETURN_NAMESPACE, 'source_order_id', config('nikolag.connections.square.order.service_identifier'));
    }
}
