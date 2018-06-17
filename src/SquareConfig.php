<?php

namespace Nikolag\Square;

use Nikolag\Core\CoreConfig;
use SquareConnect\Api\OrdersApi;
use SquareConnect\Configuration;
use SquareConnect\Api\CustomersApi;
use SquareConnect\Api\LocationsApi;
use SquareConnect\Api\TransactionsApi;
use Nikolag\Core\Contracts\ConfigContract;

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
     * SquareConfig constructor.
     *
     * @throws \Nikolag\Core\Exceptions\InvalidConfigurationException
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = config('nikolag.connections.square');
        $this->checkConfigValidity($this->config);
        $this->setAccessToken($this->config['access_token']);
        $this->locationsAPI = new LocationsApi();
        $this->customersAPI = new CustomersApi();
        $this->transactionsAPI = new TransactionsApi();
        $this->ordersAPI = new OrdersApi();
    }

    /**
     * Access token for square.
     *
     * @param string $accessToken
     *
     * @return void
     */
    public function setAccessToken(string $accessToken)
    {
        Configuration::getDefaultConfiguration()->setAccessToken($accessToken);
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
     * Getter for config.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
