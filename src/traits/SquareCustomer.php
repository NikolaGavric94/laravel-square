<?php
namespace Nikolag\Square\Traits;

use Nikolag\Square\SquareCustomer as UnderlyingSquareCustomer;

trait SquareCustomer {
	/**
     * Get the entity's customers.
     */
    public function customers()
    {
        return $this->belongsToMany('Nikolag\Square\Customer');
    }

    /**
     * Returns a boolean
     */
    public function hasCustomer(string $email)
    {
        return $this->customers->where('email', '=', $email)->count() >= 1;
    }

    /**
     * Get the underlying Square Customer.
     */
    public function asSquareCustomer()
    {
        return new UnderlyingSquareCustomer($this->attributes);
    }
}