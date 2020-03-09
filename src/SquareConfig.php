<?php

namespace Nikolag\Square;

use Nikolag\Core\Contracts\ConfigContract;
use Nikolag\Core\CoreConfig;
use SquareConnect\Api\CustomersApi;
use SquareConnect\Api\LocationsApi;
use SquareConnect\Api\OrdersApi;
use SquareConnect\Api\PaymentsApi;
use SquareConnect\Api\TransactionsApi;
use SquareConnect\ApiClient;
use SquareConnect\Configuration;

class SquareConfig extends CoreConfig implements ConfigContract
{
    /**
     * @var \SquareConnect\Api\LocationsApi
     */
    public $locationsAPI;
    /**
     * @var \SquareConnect\Api\CustomersApi
     */
    public $customersAPI;
    /**
     * @var \SquareConnect\Api\TransactionsApi
     */
    public $transactionsAPI;
    /**
     * @var \SquareConnect\Api\OrdersApi
     */
    public $ordersAPI;
    /**
     * @var \SquareConnect\Api\PaymentsApi
     */
    public $paymentsAPI;

    /**
     * SquareConfig constructor.
     *
     * @throws \Nikolag\Core\Exceptions\InvalidConfigurationException
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = config('nikolag.connections.square');
        $this->checkConfigValidity($this->config);
        $squareConfig = new Configuration();
        if (array_key_exists('sandbox', $this->config) && $this->config['sandbox']) {
            $squareConfig->setHost('https://connect.squareupsandbox.com');
        } else {
            $squareConfig->setHost('https://connect.squareup.com');
        }
        $squareConfig->setAccessToken($this->config['access_token']);
        $api_client = new ApiClient($squareConfig);
        $this->locationsAPI = new LocationsApi($api_client);
        $this->customersAPI = new CustomersApi($api_client);
        $this->transactionsAPI = new TransactionsApi($api_client);
        $this->ordersAPI = new OrdersApi($api_client);
        $this->paymentsAPI = new PaymentsApi($api_client);
    }

    /**
     * Api for locations.
     *
     * @return \SquareConnect\Api\LocationsApi
     */
    public function locationsAPI()
    {
        return $this->locationsAPI;
    }

    /**
     * Api for customers.
     *
     * @return \SquareConnect\Api\CustomersApi
     */
    public function customersAPI()
    {
        return $this->customersAPI;
    }

    /**
     * Api for transactions.
     *
     * @return \SquareConnect\Api\TransactionsApi
     */
    public function transactionsAPI()
    {
        return $this->transactionsAPI;
    }

    /**
     * Api for orders.
     *
     * @return \SquareConnect\Api\ordersApi
     */
    public function ordersAPI()
    {
        return $this->ordersAPI;
    }

    /**
     * Api for payments.
     *
     * @return PaymentsApi
     */
    public function paymentsAPI()
    {
        return $this->paymentsAPI;
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
