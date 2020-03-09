<?php
/**
 * Created by PhpStorm.
 * User: nikola
 * Date: 6/20/18
 * Time: 03:00.
 */

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\SquareConfig;
use Nikolag\Square\Tests\TestCase;

class SquareConfigTest extends TestCase
{
    /**
     * @var SquareConfig
     */
    private $config;

    public function assertPreConditions(): void
    {
        parent::assertPreConditions();
        $this->config = $this->app->make(SquareConfig::class);
    }

    /**
     * Config OK.
     *
     * @return void
     */
    public function test_square_config_ok()
    {
        $this->assertNotNull($this->config->locationsAPI());
        $this->assertInstanceOf('\SquareConnect\Api\LocationsApi', $this->config->locationsAPI());
        $this->assertNotNull($this->config->customersAPI());
        $this->assertInstanceOf('\SquareConnect\Api\CustomersApi', $this->config->customersAPI());
        $this->assertNotNull($this->config->ordersAPI());
        $this->assertInstanceOf('\SquareConnect\Api\OrdersApi', $this->config->ordersAPI());
        $this->assertNotNull($this->config->transactionsAPI());
        $this->assertInstanceOf('\SquareConnect\Api\PaymentsApi', $this->config->paymentsAPI());
        $this->assertNotNull($this->config->paymentsAPI());
        $this->assertInstanceOf('\SquareConnect\Api\TransactionsApi', $this->config->transactionsAPI());
        $this->assertNotNull($this->config->getConfig());
    }
}
