<?php

namespace Nikolag\Square;

use Nikolag\Core\CoreConfig;
use Nikolag\Core\Exceptions\InvalidConfigurationException;
use Square\Apis\CatalogApi;
use Square\Apis\CustomersApi;
use Square\Apis\LocationsApi;
use Square\Apis\OrdersApi;
use Square\Apis\PaymentsApi;
use Square\Apis\TransactionsApi;
use Square\SquareClient;

class SquareConfig extends CoreConfig
{
    /**
     * @var SquareClient
     */
    private SquareClient $squareClient;

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
     * Api for catalog.
     *
     * @return CatalogApi
     */
    public function catalogAPI(): CatalogApi
    {
        return $this->squareClient->getCatalogApi();
    }

    /**
     * Api for locations.
     *
     *
     * @return LocationsApi
     */
    public function locationsAPI(): LocationsApi
    {
        return $this->squareClient->getLocationsApi();
    }

    /**
     * Api for customers.
     *
     * @return CustomersApi
     */
    public function customersAPI(): CustomersApi
    {
        return $this->squareClient->getCustomersApi();
    }

    /**
     * Api for transactions.
     *
     * @deprecated cf. https://developer.squareup.com/docs/build-basics/api-lifecycle#deprecated
     * @see https://developer.squareup.com/docs/payments-api/migrate-from-transactions-api
     *
     * @return TransactionsApi
     */
    public function transactionsAPI(): TransactionsApi
    {
        return $this->squareClient->getTransactionsApi();
    }

    /**
     * Api for orders.
     *
     * @return OrdersApi
     */
    public function ordersAPI(): OrdersApi
    {
        return $this->squareClient->getOrdersApi();
    }

    /**
     * Api for payments.
     *
     * @return PaymentsApi
     */
    public function paymentsAPI(): PaymentsApi
    {
        return $this->squareClient->getPaymentsApi();
    }

    /**
     * Getter for config.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
