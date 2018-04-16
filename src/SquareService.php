<?php

namespace Nikolag\Square;

use Nikolag\Core\Abstracts\CorePaymentService;
use Nikolag\Square\Builders\OrderBuilder;
use Nikolag\Square\Builders\ProductBuilder;
use Nikolag\Square\Builders\SquareRequestBuilder;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Exceptions\AlreadyUsedSquareProductException;
use Nikolag\Square\Exceptions\InvalidSquareAmountException;
use Nikolag\Square\Exceptions\InvalidSquareCurrencyException;
use Nikolag\Square\Exceptions\InvalidSquareCvvException;
use Nikolag\Square\Exceptions\InvalidSquareExpirationDateException;
use Nikolag\Square\Exceptions\InvalidSquareNonceException;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\InvalidSquareZipcodeException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Exceptions\UsedSquareNonceException;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use SquareConnect\ApiException;
use SquareConnect\Model\CreateCustomerRequest;
use SquareConnect\Model\CreateOrderRequest;
use stdClass;

class SquareService extends CorePaymentService implements SquareServiceContract
{
    /**
     * @var stdClass
     */
    private $orderCopy;
    /**
     * @var OrderBuilder
     */
    private $orderBuilder;
    /**
     * @var SquareRequestBuilder
     */
    private $squareBuilder;
    /**
     * @var string
     */
    private $locationId;
    /**
     * @var string
     */
    private $currency;

    public function __construct(SquareConfig $squareConfig)
    {
        $this->config = $squareConfig;
        $this->orderCopy = new stdClass();
        $this->orderBuilder = new OrderBuilder();
        $this->squareBuilder = new SquareRequestBuilder();
        $this->productBuilder = new ProductBuilder();
    }

    /**
     * Calculates order total.
     *
     * @return float
     */
    public function _calculateTotalOrderCost()
    {
        $noDeductiblesCost = 0;
        // Calculate gross amount (total)
        foreach ($this->orderCopy->products as $product) {
            $productPivot = $product->productPivot;
            $product = $product->product;

            $totalPrice = $productPivot->quantity * $product->price;
            $currentPrice = $totalPrice;
            $noDeductiblesCost += $currentPrice;
        }
        // Apply discounts on order and product level
        $currentOrderPrice = $noDeductiblesCost;
        // Order level discounts
        foreach ($this->orderCopy->discounts as $orderDiscount) {
            //Product level discounts
            foreach ($this->orderCopy->products as $currProduct) {
                $productPivot = $currProduct->productPivot;
                $product = $currProduct->product;

                $totalProductPrice = $productPivot->quantity * $product->price;
                $currentProductPrice = $totalProductPrice;

                // Calculate product discounts
                foreach ($currProduct->discounts as $discount) {
                    if ($discount->amount && !$discount->percentage) {
                        $noDeductiblesCost -= $discount->amount;
                        $currentProductPrice -= $discount->amount;
                    }
                    if ($discount->percentage && !$discount->amount) {
                        $noDeductiblesCost -= $totalProductPrice * $discount->percentage / 100;
                        $currentProductPrice -= $totalProductPrice * $discount->percentage / 100;
                    }
                }
                //Algorithm based off of https://docs.connect.squareup.com/articles/orders-api-overview
                $discountAmount = ($orderDiscount->percentage) ? $currentOrderPrice * $orderDiscount->percentage / 100 : $orderDiscount->amount;
                $noDeductiblesCost -= $discountAmount;
            }
        }

        // Order level taxes
        foreach ($this->orderCopy->taxes as $orderTax) {
            //Product level taxes
            foreach ($this->orderCopy->products as $currProduct) {
                $productPivot = $currProduct->productPivot;
                $product = $currProduct->product;

                $totalProductPrice = $productPivot->quantity * $product->price;
                $currentProductPrice = $totalProductPrice;

                // Calculate product discounts
                foreach ($currProduct->discounts as $discount) {
                    if ($discount->amount && !$discount->percentage) {
                        $currentProductPrice -= $discount->amount;
                    }
                    if ($discount->percentage && !$discount->amount) {
                        $currentProductPrice -= $totalProductPrice * $discount->percentage / 100;
                    }
                }

                // Calculate product taxes
                foreach ($currProduct->taxes as $tax) {
                    if ($tax->type === Constants::TAX_ADDITIVE) {
                        $noDeductiblesCost += $currentProductPrice * $tax->percentage / 100;
                    }
                }
                // Calculate order taxes
                if ($orderTax->type === Constants::TAX_ADDITIVE) {
                    $taxAmount = $currentOrderPrice * $orderTax->percentage / 100;
                    $noDeductiblesCost += $taxAmount;
                }
            }
        }

        return $noDeductiblesCost;
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
     * Save a customer.
     *
     * @return void
     */
    private function _saveCustomer()
    {
        if (!$this->getCustomer()->payment_service_id) {
            $response = $this->config->customersAPI->createCustomer($this->getCreateCustomerRequest());
            $this->getCustomer()->payment_service_id = $response->getCustomer()->getId();
        } else {
            $this->config->customersAPI->updateCustomer($this->getCustomer()->payment_service_id, $this->getCreateCustomerRequest());
        }

        $this->getCustomer()->save();
        // If merchant exists and if merchant doesn't have customer
        if ($this->getMerchant() && !$this->getMerchant()->hasCustomer($this->getCustomer()->email)) {
            // Attach seller to the buyer
            $this->getCustomer()->merchants()->attach($this->getMerchant()->id);
        }
    }

    /**
     * Save order to database and if required
     * also save to square vault.
     *
     * @param bool $saveToSquare
     *
     * @return void
     */
    private function _saveOrder(bool $saveToSquare = false)
    {
        $this->order = $this->orderBuilder->buildOrderFromOrderCopy($this->getOrder(), $this->orderCopy);
        //If property locationId doesn't exist throw error
        if (!$this->locationId) {
            throw new MissingPropertyException('$locationId property is missing', 500);
        }
        //If order doesn't have any products throw error
        if ($this->getOrder()->products()->count() == 0) {
            throw new InvalidSquareOrderException('Object Order must have at least 1 Product', 500);
        }
        //If local order doesn't have square order identifier to which to relate
        //local order
        $property = config('nikolag.connections.square.order.service_identifier');
        if (!$this->getOrder()->hasAttribute($property)) {
            throw new InvalidSquareOrderException('Table orders is missing a required column: '.$property, 500);
        }
        $orderRequest = $this->squareBuilder->buildOrderRequest($this->getOrder(), $this->currency);
        $this->setCreateOrderRequest($orderRequest);
        // If want to save to square, make a request
        if ($saveToSquare) {
            $response = $this->config->ordersAPI->createOrder($this->locationId, $this->getCreateOrderRequest());
            //Save id of a real order inside of Square to our local model for future use
            $this->getOrder()->{$property} = $response->getOrder()->getId();
        }
        $this->getOrder()->save();
    }

    /**
     * Save collected data.
     *
     * @throws \Nikolag\Square\Exception on non-2xx response
     *
     * @return self
     */
    public function save()
    {
        try {
            if ($this->getCustomer()) {
                $this->_saveCustomer();
            }
            if ($this->getOrder()) {
                $this->_saveOrder();
            }
        } catch (ApiException $exception) {
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
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required fields are missing', 500, $e);
        } catch (InvalidSquareOrderException $e) {
            throw new MissingPropertyException('Required column is missing from the table', 500, $e);
        }

        return $this;
    }

    /**
     * Charge a customer.
     *
     * @param array $data
     *
     * @throws \Nikolag\Square\Exception on non-2xx response
     *
     * @return \Nikolag\Square\Models\Transaction
     */
    public function charge(array $data)
    {
        $currency = array_key_exists('currency', $data) ? $data['currency'] : 'USD';
        $prepData = [
            'idempotency_key' => uniqid(),
            'amount_money'    => [
                'amount'   => $data['amount'],
                'currency' => $currency,
            ],
            'card_nonce' => $data['card_nonce'],
        ];

        $transaction = new Transaction(['status' => Constants::TRANSACTION_STATUS_OPENED, 'amount' => $data['amount']]);
        // Save and attach merchant
        if ($this->getMerchant()) {
            $transaction->merchant()->associate($this->getMerchant());
        }
        // Save and attach customer
        if ($this->getCustomer()) {
            try {
                $this->_saveCustomer();
                $transaction->customer()->associate($this->getCustomer());
            } catch (Exception $e) {
                throw $e;
            }
        }
        // Save and attach order
        if ($this->getOrder()) {
            try {
                // Calculate the total order amount
                $calculatedCost = $this->_calculateTotalOrderCost();
                // If order total does not match charge amount, throw error
                if ($calculatedCost != $data['amount']) {
                    throw new InvalidSquareAmountException('The charge amount does not match the order total.', 500);
                }
                // Save order to both database and square
                $this->_saveOrder(true);
                // Connect order with transaction
                $transaction->order()->associate($this->getOrder());
                // Get table column name for square id property
                $property = config('nikolag.connections.square.order.service_identifier');
                // Set order id for square from order model property for square identifier
                $prepData['order_id'] = $this->getOrder()->{$property};
            } catch (MissingPropertyException $e) {
                throw new MissingPropertyException('Required field is missing', 500, $e);
            } catch (InvalidSquareOrderException $e) {
                throw new MissingPropertyException('Required column is missing from the table', 500, $e);
            }
        }
        $transaction->save();

        try {
            $response = $this->config->transactionsAPI->charge($data['location_id'], $prepData)->getTransaction();

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
     * Transactions directly from Square API.
     *
     * @param array $options
     *
     * @throws \Nikolag\Square\Exception on non-2xx response
     *
     * @return \SquareConnect\Model\ListLocationsResponse
     */
    public function transactions(array $options)
    {
        $transactions = $this->config->transactionsAPI->listTransactions($options['location_id'], $options['begin_time'], $options['end_time'], $options['sort_order'], $options['cursor']);

        return $transactions;
    }

    /**
     * Add a product to the order.
     *
     * @param mixed  $product
     * @param int    $quantity
     * @param string $currency
     *
     * @return self
     */
    public function addProduct($product, int $quantity = 1, string $currency = 'USD')
    {
        //Product class
        $productClass = Constants::PRODUCT_NAMESPACE;

        try {
            if (is_a($product, $productClass)) {
                $productPivot = $this->productBuilder->addProductFromModel($this->getOrder(), $product, $quantity, $currency);
            } else {
                $productPivot = $this->productBuilder->addProductFromArray($this->getOrder(), $product, $quantity, $currency);
            }
            // Check if order already has this product
            if (!Util::hasProduct($this->orderCopy->products, $productPivot->product)) {
                $this->orderCopy->products->push($productPivot);
            } else {
                throw new AlreadyUsedSquareProductException('Product is already part of the order', 500);
            }
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required field is missing', 500, $e);
        }

        return $this;
    }

    /**
     * @return CreateCustomerRequest
     */
    public function getCreateCustomerRequest()
    {
        return $this->createCustomerRequest;
    }

    /**
     * @param CreateCustomerRequest $createCustomerRequest
     *
     * @return self
     */
    public function setCreateCustomerRequest(CreateCustomerRequest $createCustomerRequest)
    {
        $this->createCustomerRequest = $createCustomerRequest;

        return $this;
    }

    /**
     * @return CreateOrderRequest
     */
    public function getCreateOrderRequest()
    {
        return $this->createOrderRequest;
    }

    /**
     * @param CreateOrderRequest $createOrderRequest
     *
     * @return self
     */
    public function setCreateOrderRequest(CreateOrderRequest $createOrderRequest)
    {
        $this->createOrderRequest = $createOrderRequest;

        return $this;
    }

    /**
     * @param mixed $customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {
        $customerClass = Constants::CUSTOMER_NAMESPACE;

        if (is_a($customer, $customerClass)) {
            $this->customer = $customer;
        } elseif (is_array($customer)) {
            $this->customer = new $customerClass($customer);
        }

        if ($customer) {
            $customerRequest = $this->squareBuilder->buildCustomerRequest($this->customer);
            $this->setCreateCustomerRequest($customerRequest);
        }

        return $this;
    }

    /**
     * Setter for order.
     *
     * @param mixed  $order
     * @param string $locationId
     * @param string $currency
     *
     * @return self
     */
    public function setOrder($order, string $locationId, string $currency = 'USD')
    {
        //Order class
        $orderClass = config('nikolag.connections.square.order.namespace');

        if (!$order) {
            throw new MissingPropertyException('$order property is missing', 500);
        }
        if (!$locationId) {
            throw new MissingPropertyException('$locationId property is missing', 500);
        }

        $this->locationId = $locationId;
        $this->currency = $currency;

        if (is_a($order, $orderClass)) {
            $this->order = $order;
            $this->orderCopy = $this->orderBuilder->buildOrderCopyFromModel($order);
        } elseif (is_array($order)) {
            $this->order = new $orderClass($order);
            $this->orderCopy = $this->orderBuilder->buildOrderCopyFromArray($order);
        }

        return $this;
    }
}
