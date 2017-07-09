<?php

namespace Nikolag\Square;

use Nikolag\Square\Contracts\SquareContract;
use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareConfig;
use Nikolag\Square\Utils\Constants;
use SquareConnect\Model\CreateCustomerRequest;

class SquareCustomer implements SquareContract {
    /**
     * @var CreateCustomerRequest
     */
    private $squareCustomerRequest;
    /**
     * @var Customer|null
     */
    private $customer;
    /**
     * @var any
     */
    private $merchant;
    /**
     * @var SquareConfig
     */
    private $squareConfig;

	function __construct(SquareConfig $squareConfig) {
        $this->squareConfig = $squareConfig;
    }

    /**
     * Create customer request.
     * 
     * @param any  $customer the customer
     * @return void
     */
    private function buildCustomerRequest($customer)
    {
        $data = array(
            'given_name' => $customer->first_name,
            'family_name' => $customer->last_name,
            'company_name' => $customer->company_name,
            'nickname' => $customer->nickname,
            'email_address' => $customer->email,
            'phone_number' => $customer->phone,
            'reference_id' => $customer->owner_id,
            'note' => $customer->note
        );
        $customerRequest = new CreateCustomerRequest($data);
        $this->setSquareCustomerRequest($customerRequest);
    }

    /**
     * List locations.
     * 
     * @return \SquareConnect\Model\ListLocationsResponse
     */
    public function locations()
    {
        return $this->squareConfig->locationsAPI->listLocations();
    }

    /**
     * Save customer.
     * 
     * @return void
     */
    public function save()
    {
        try {
            if($this->getCustomer())
            {
                if(!$this->getCustomer()->square_id)
                {
                    $response = $this->squareConfig->customersAPI->createCustomer($this->getSquareCustomerRequest());
                    $this->getCustomer()->square_id = $response->getCustomer()->getId();
                    $this->getCustomer()->save();

                    if($this->getMerchant())
                    {
                        $this->getCustomer()->merchants()->attach($this->getMerchant()->id);
                    }
                }
                else
                {
                    $this->squareConfig->customersAPI->updateCustomer($this->getCustomer()->square_id, $this->getSquareCustomerRequest());
                }
            } else
            {

            }
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
     * Charge a customer.
     * 
     * @param float $amount 
     * @param string $card_nonce 
     * @param string $location_id 
     * @return \Nikolag\Square\Models\Transaction
     * @throws \Nikolag\Square\Exception on non-2xx response
     */
    public function charge(float $amount, string $card_nonce, string $location_id)
    {
        $transaction = new Transaction(['status' => Constants::TRANSACTION_STATUS_OPENED, 'amount' => $amount]);
        if($this->getMerchant())
        {
            $transaction->merchant()->associate($this->getMerchant());
        }
        if($this->getCustomer())
        {
            $transaction->customer()->associate($this->getCustomer());
        }
        $transaction->save();

        try {
            $response = $this->squareConfig->transactionsAPI->charge($location_id, array(
                'idempotency_key' => uniqid(),
                  'amount_money' => array(
                    'amount' => $amount,
                    'currency' => 'USD'
                  ),
                  'card_nonce' => $card_nonce,
            ))->getTransaction();

            $transaction->status = Constants::TRANSACTION_STATUS_PASSED;
            $transaction->save();

        } catch (Exception $e) {
            $transaction->status = Constants::TRANSACTION_STATUS_FAILED;
            $transaction->save();

            throw $e;
        }

        return $transaction;
    }

    /**
     * List transactions.
     * 
     * @param string $locationId 
     * @param type|null $begin_time 
     * @param type|null $end_time 
     * @param type|null $cursor 
     * @param type|string $sort_order 
     * @return \SquareConnect\Model\ListTransactionsResponse
     */
    public function transactions(string $locationId, $begin_time = null, $end_time = null, $cursor = null, $sort_order = 'desc')
    {
        $transactions = $this->squareConfig->transactionsAPI->listTransactions($location_id, $begin_time, $end_time, $sort_order, $cursor);
        return $transactions;
    }

    /**
     * @return CreateCustomerRequest
     */
    public function getSquareCustomerRequest()
    {
        return $this->squareCustomerRequest;
    }

    /**
     * @param CreateCustomerRequest $squareCustomerRequest
     *
     * @return self
     */
    public function setSquareCustomerRequest(CreateCustomerRequest $squareCustomerRequest)
    {
        $this->squareCustomerRequest = $squareCustomerRequest;

        return $this;
    }

    /**
     * @return Customer|null
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param Customer|null $customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {
        if($customer instanceof Customer) $this->customer = $customer;
        else if(is_array($customer)) $this->customer = new Customer($customer);

        if($customer) $this->buildCustomerRequest($this->customer);

        return $this;
    }

    /**
     * @return any
     */
    public function getMerchant()
    {
        return $this->merchant;
    }

    /**
     * @param any $merchant
     *
     * @return self
     */
    public function setMerchant($merchant)
    {
        $this->merchant = $merchant;

        return $this;
    }

    /**
     * @return SquareConfig
     */
    public function getSquareConfig()
    {
        return $this->squareConfig;
    }

    /**
     * @param SquareConfig $squareConfig
     *
     * @return self
     */
    public function setSquareConfig(SquareConfig $squareConfig)
    {
        $this->squareConfig = $squareConfig;

        return $this;
    }
}