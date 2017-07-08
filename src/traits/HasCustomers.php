<?php
namespace Nikolag\Square\Traits;

use Nikolag\Square\SquareCustomer;
use Nikolag\Square\Utils\Constants;

trait HasCustomers {

    /**
     * Get the entity's customers.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers()
    {
        return $this->belongsToMany(Constants::CUSTOMER_NAMESPACE, 'nikolag_customer_user', 'customer_id', 'reference_id');
    }

    /**
     * Does entity have a customer.
     * 
     * @param string $email 
     * @return \Nikolag\Square\Model\Customer|bool
     */
    public function hasCustomer(string $email)
    {
        $query = $this->customers()->where('email', '=', $email);
        return $query->exists() ? 
                $query->first() : FALSE;
    }

    /**
     * All transactions.
     * 
     * @return string
     */
    public function transactions()
    {
        return 'Not implemented yet.';
    }

    /**
     * Paid transactions.
     * 
     * @param type $query 
     * @return string
     */
    public function scopePassedTransactions($query)
    {
        return 'Not implemented yet.';
    }

    /**
     * Pending transactions.
     * 
     * @param type $query 
     * @return string
     */
    public function scopeOpenedTransactions($query)
    {
        return 'Not implemented yet.';
    }

    /**
     * Failed transactions.
     * 
     * @param type $query 
     * @return string
     */
    public function scopeFailedTransactions($query)
    {
        return 'Not implemented yet.';
    }

    /**
     * Get the underlying Square Customer.
     * 
     * @return \Nikolag\Square\SquareCustomer
     */
    public function asSquareCustomer()
    {
        return new SquareCustomer($this->attributes);
    }
}