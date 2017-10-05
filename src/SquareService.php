<?php

namespace Nikolag\Square;

use Nikolag\Core\Abstracts\CorePaymentService;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareConfig;
use Nikolag\Square\Utils\Constants;
use SquareConnect\Model\CreateCustomerRequest;

class SquareService extends CorePaymentService implements SquareServiceContract
{
    /**
     * @var CreateCustomerRequest
     */
    private $squareServiceRequest;

    public function __construct(SquareConfig $squareConfig)
    {
        $this->config = $squareConfig;
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
        $this->setSquareServiceRequest($customerRequest);
    }

    /**
     * List locations.
     *
     * @return \SquareConnect\Model\ListLocationsResponse
     */
    public function locations()
    {
        return $this->config->locationsAPI->listLocations();
    }

    /**
     * Save customer.
     *
     * @return void
     * @throws \Nikolag\Square\Exception on failed save
     */
    public function save()
    {
        try {
            if ($this->getCustomer()) {
                if (!$this->getCustomer()->payment_service_id) {
                    $response = $this->config->customersAPI->createCustomer($this->getSquareServiceRequest());
                    $this->getCustomer()->payment_service_id = $response->getCustomer()->getId();
                    $this->getCustomer()->save();

                    if ($this->getMerchant()) {
                        $this->getCustomer()->merchants()->attach($this->getMerchant()->id);
                    }
                } else {
                    $this->config->customersAPI->updateCustomer($this->getCustomer()->payment_service_id, $this->getSquareServiceRequest());
                }
            } else {
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Charge a customer.
     *
     * @param array $data
     * @return \Nikolag\Square\Models\Transaction
     * @throws \Nikolag\Square\Exception on non-2xx response
     */
    public function charge(array $data) {
        $transaction = new Transaction(['status' => Constants::TRANSACTION_STATUS_OPENED, 'amount' => $data['amount']]);
        if ($this->getMerchant()) {
            $transaction->merchant()->associate($this->getMerchant());
        }
        if ($this->getCustomer()) {
            $transaction->customer()->associate($this->getCustomer());
        }
        $transaction->save();

        try {
            $response = $this->config->transactionsAPI->charge($data['location_id'], array(
                'idempotency_key' => uniqid(),
                'amount_money' => [
                    'amount' => $data['amount'],
                    'currency' => array_key_exists('currency', $data)?$data['currency']:'USD'
                ],
                'card_nonce' => $data['card_nonce'],
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
     * @param array $data
     * @return \SquareConnect\Model\ListTransactionsResponse
     */
    public function transactions(array $data) {
        $transactions = $this->config->transactionsAPI->listTransactions($data['location_id'], $data['begin_time'], $data['end_time'], $data['sort_order'], $data['cursor']);
        return $transactions;
    }

    /**
     * @return CreateCustomerRequest
     */
    public function getSquareServiceRequest()
    {
        return $this->squareServiceRequest;
    }

    /**
     * @param CreateCustomerRequest $squareServiceRequest
     *
     * @return self
     */
    public function setSquareServiceRequest(CreateCustomerRequest $squareServiceRequest)
    {
        $this->squareServiceRequest = $squareServiceRequest;

        return $this;
    }

    /**
     * @param Customer|null $customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {
        if ($customer instanceof Customer) {
            $this->customer = $customer;
        } elseif (is_array($customer)) {
            $this->customer = new Customer($customer);
        }

        if ($customer) {
            $this->buildCustomerRequest($this->customer);
        }

        return $this;
    }
}
