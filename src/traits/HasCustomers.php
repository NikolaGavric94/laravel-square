<?php
namespace Nikolag\Square\Traits;

use Nikolag\Square\Facades\Square;
use Nikolag\Square\SquareCustomer;
use Nikolag\Square\Utils\Constants;

trait HasCustomers {

    /**
     * Retrieve merchant customers.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers()
    {
        return $this->belongsToMany(Constants::CUSTOMER_NAMESPACE, 'nikolag_customer_user', 'customer_id', 'owner_id');
    }

    /**
     * Retrieve customer if he exists, otherwise return false.
     * 
     * @param string $email 
     * @return \Nikolag\Square\Model\Customer|false
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Constants::TRANSACTION_NAMESPACE, 'merchant_id', config('nikolag.user.identifier'));
    }

    /**
     * Paid transactions.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePassedTransactions($query)
    {
        return $query->with('transactions', function($q) {
            $q->where('status', '=', Constants::TRANSACTION_STATUS_PASSED);
        });
    }

    /**
     * Pending transactions.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpenedTransactions($query)
    {
        return $query->with('transactions', function($q) {
            $q->where('status', '=', Constants::TRANSACTION_STATUS_OPENED);
        });
    }

    /**
     * Failed transactions.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailedTransactions($query)
    {
        return $query->with('transactions', function($q) {
            $q->where('status', '=', Constants::TRANSACTION_STATUS_FAILED);
        });
    }

    /**
     * Charge a customer.
     * 
     * @param float $amount 
     * @param string $nonce 
     * @param string $location_id 
     * @param any $customer
     * @return \Nikolag\Square\Models\Transaction
     */
    public function charge(float $amount, string $nonce, string $location_id, $customer = null)
    {
        return Square::setMerchant($this)->setCustomer($customer)->charge($amount, $nonce, $location_id, $this->attributes[config('nikolag.user.identifier')]);
    }

    /**
     * Save a customer.
     * 
     * @param array $customer 
     * @return void
     */
    public function saveCustomer(array $customer)
    {
        Square::setMerchant($this)->setCustomer($customer)->save();
    }
}