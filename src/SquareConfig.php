<?php

namespace Nikolag\Square;

use Nikolag\Core\Contracts\ConfigContract;
use Nikolag\Core\CoreConfig;
use Nikolag\Core\Exceptions\InvalidConfigurationException;
use Square\Apis\CustomersApi;
use Square\Apis\LocationsApi;
use Square\Apis\OrdersApi;
use Square\Apis\PaymentsApi;
use Square\Apis\TransactionsApi;
use Square\SquareClient;

class SquareConfig extends CoreConfig implements ConfigContract
{
    /**
     * @var SquareClient
     */
    private $squareClient;

    /**
     * SquareConfig constructor.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = config('nikolag.connections.square');
        $this->checkConfigValidity($this->config);
        $isSandbox = array_key_exists('sandbox', $this->config) && $this->config['sandbox'];
        $environment = $isSandbox ? 'sandbox' : 'production';
        $this->squareClient = new SquareClient([
            'accessToken' => $this->config['access_token'],
            'environment' => $environment,
        ]);
    }

    /**
     * Api for locations.
     *
     * @return LocationsApi
     */
    public function locationsAPI()
    {
        return $this->squareClient->getLocationsApi();
    }

    /**
     * Api for customers.
     *
     * @return CustomersApi
     */
    public function customersAPI()
    {
        return $this->squareClient->getCustomersApi();
    }

    /**
     * Api for transactions.
     *
     * @return TransactionsApi
     */
    public function transactionsAPI()
    {
        return $this->squareClient->getTransactionsApi();
    }

    /**
     * Api for orders.
     *
     * @return OrdersApi
     */
    public function ordersAPI()
    {
        return $this->squareClient->getOrdersApi();
    }

    /**
     * Api for payments.
     *
     * @return PaymentsApi
     */
    public function paymentsAPI()
    {
        return $this->squareClient->getPaymentsApi();
    }

    /**
     * Getter for config.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
