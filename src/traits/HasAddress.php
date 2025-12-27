<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Nikolag\Square\Models\Address;
use Square\Models\Address as SquareAddress;

trait HasAddress
{
    /**
     * Get the address relationship.
     *
     * @return MorphOne
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /**
     * Check if the model has an address.
     *
     * @return bool
     */
    public function hasAddress(): bool
    {
        return $this->address()->exists();
    }

    /**
     * Get the address as a Square Address object.
     *
     * @return SquareAddress|null
     */
    public function getSquareAddress(): ?SquareAddress
    {
        if (! $this->address) {
            return null;
        }

        return $this->address->toSquareAddress();
    }

    /**
     * Create or update address from Square Address object.
     *
     * @param  SquareAddress  $squareAddress
     * @return Address
     */
    public function syncAddressFromSquare(SquareAddress $squareAddress): Address
    {
        $address = $this->address()->firstOrNew([]);
        $address->updateFromSquareAddress($squareAddress);
        $this->address()->save($address);

        return $address;
    }
}
