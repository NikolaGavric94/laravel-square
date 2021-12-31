<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nikolag\Core\Exceptions\Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Utils\Constants;

trait HasCustomers
{
    /**
     * Retrieve merchant customers.
     *
     * @return BelongsToMany
     */
    public function customers()
    {
        return $this->belongsToMany(Constants::CUSTOMER_NAMESPACE, 'nikolag_customer_user', 'owner_id', 'customer_id');
    }

    /**
     * Retrieve customer if he exists, otherwise return false.
     *
     * @param  string  $email
     * @return mixed
     */
    public function hasCustomer(string $email)
    {
        $query = $this->customers()->where('email', '=', $email);

        return $query->exists() ?
                $query->first() : false;
    }

    /**
     * All transactions.
     *
     * @return HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Constants::TRANSACTION_NAMESPACE, 'merchant_id', config('nikolag.connections.square.user.identifier'));
    }

    /**
     * Paid transactions.
     *
     * @return HasMany
     */
    public function passedTransactions()
    {
        return $this->_byTransactionStatus(Constants::TRANSACTION_STATUS_PASSED);
    }

    /**
     * Pending transactions.
     *
     * @return HasMany
     */
    public function openedTransactions()
    {
        return $this->_byTransactionStatus(Constants::TRANSACTION_STATUS_OPENED);
    }

    /**
     * Failed transactions.
     *
     * @return HasMany
     */
    public function failedTransactions()
    {
        return $this->_byTransactionStatus(Constants::TRANSACTION_STATUS_FAILED);
    }

    /**
     * Charge a customer.
     *
     * @param  float  $amount
     * @param  string  $nonce
     * @param  string  $location_id
     * @param  array  $options
     * @param  mixed  $customer
     * @param  string  $currency
     * @return Transaction
     *
     * @throws Exception
     */
    public function charge(float $amount, string $nonce, string $location_id, array $options = [], $customer = null, string $currency = 'USD')
    {
        return Square::setMerchant($this)->setCustomer($customer)->charge(
            array_merge(['amount' => $amount, 'source_id' => $nonce, 'location_id' => $location_id, 'currency' => $currency], $options)
        );
    }

    /**
     * Save a customer.
     *
     * @param  array  $customer
     * @return void
     */
    public function saveCustomer(array $customer)
    {
        Square::setMerchant($this)->setCustomer($customer)->save();
    }

    /**
     * Model function, return all transactions by status.
     *
     * @param  string  $status
     * @return HasMany
     */
    private function _byTransactionStatus(string $status)
    {
        return $this->transactions()->where(function ($query) use ($status) {
            $query->where('merchant_id', '=', $this->attributes[config('nikolag.connections.square.user.identifier')])
                ->where('status', '=', $status);
        });
    }
}
