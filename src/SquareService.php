<?php

namespace Nikolag\Square;

use Nikolag\Core\Abstracts\CorePaymentService;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Exceptions\InvalidSquareCurrencyException;
use Nikolag\Square\Exceptions\InvalidSquareCvvException;
use Nikolag\Square\Exceptions\InvalidSquareExpirationDateException;
use Nikolag\Square\Exceptions\InvalidSquareNonceException;
use Nikolag\Square\Exceptions\InvalidSquareZipcodeException;
use Nikolag\Square\Exceptions\UsedSquareNonceException;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareConfig;
use Nikolag\Square\Utils\Constants;
use SquareConnect\ApiException;
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
        } catch (ApiException $exception) {
            //Set exception to be first in array of errors
            $exception = $exceptionJSON->getResponseBody()->errors[0];

            if ($exceptionJSON->category == Constants::INVALID_REQUEST_ERROR) {
                if ($exceptionJSON->code == Constants::NOT_FOUND) {
                    $exception = new InvalidSquareNonceException($exceptionJSON->detail, 404, $exception);
                } elseif ($exceptionJSON->code == Constants::INVALID_VALUE) {
                    $exception = new InvalidSquareCurrencyException($exceptionJSON->detail, 400, $exception);
                } elseif ($exceptionJSON->code == Constants::NONCE_USED) {
                    $exception = new UsedSquareNonceException($exceptionJSON->detail, 400, $exception);
                }
            } elseif ($exceptionJSON->category == Constants::PAYMENT_METHOD_ERROR) {
                if ($exceptionJSON->code == Constants::INVALID_EXPIRATION) {
                    $exception = new InvalidSquareExpirationDateException($exceptionJSON->detail, 400, $exception);
                } elseif ($exceptionJSON->code == Constants::VERIFY_POSTAL_CODE) {
                    $exception = new InvalidSquareZipcodeException($exceptionJSON->detail, 402, $exception);
                } elseif ($exceptionJSON->code == Constants::VERIFY_CVV) {
                    $exception = new InvalidSquareCvvException($exceptionJSON->detail, 402, $exception);
                }
            }

            throw $exception;
        }
    }

    /**
     * Charge a customer.
     *
     * @param array $data
     * @return \Nikolag\Square\Models\Transaction
     * @throws \Nikolag\Square\Exception on non-2xx response
     */
    public function charge(array $data)
    {
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
        } catch (ApiException $exception) {
            $transaction->status = Constants::TRANSACTION_STATUS_FAILED;
            $transaction->save();
            //Set exception to be first in array of errors
            $exceptionJSON = $exception->getResponseBody()->errors[0];

            if ($exceptionJSON->category == Constants::INVALID_REQUEST_ERROR) {
                if ($exceptionJSON->code == Constants::NOT_FOUND) {
                    $exception = new InvalidSquareNonceException($exceptionJSON->detail, 404, $exception);
                } elseif ($exceptionJSON->code == Constants::INVALID_VALUE) {
                    $exception = new InvalidSquareCurrencyException($exceptionJSON->detail, 400, $exception);
                } elseif ($exceptionJSON->code == Constants::NONCE_USED) {
                    $exception = new UsedSquareNonceException($exceptionJSON->detail, 400, $exception);
                }
            } elseif ($exceptionJSON->category == Constants::PAYMENT_METHOD_ERROR) {
                if ($exceptionJSON->code == Constants::INVALID_EXPIRATION) {
                    $exception = new InvalidSquareExpirationDateException($exceptionJSON->detail, 400, $exception);
                } elseif ($exceptionJSON->code == Constants::VERIFY_POSTAL_CODE) {
                    $exception = new InvalidSquareZipcodeException($exceptionJSON->detail, 402, $exception);
                } elseif ($exceptionJSON->code == Constants::VERIFY_CVV) {
                    $exception = new InvalidSquareCvvException($exceptionJSON->detail, 402, $exception);
                }
            }

            throw $exception;
        }

        return $transaction;
    }

    /**
     * List transactions.
     *
     * @param array $data
     * @return \SquareConnect\Model\ListTransactionsResponse
     */
    public function transactions(array $data)
    {
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
