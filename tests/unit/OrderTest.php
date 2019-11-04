<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class OrderTest extends TestCase
{
    /**
     * Order creation with relationships.
     *
     * @return void
     */
    public function test_order_make()
    {
        $discounts = factory(Discount::class, 5)->create();
        $taxes = factory(Tax::class, 3)->create();
        $order = factory(Order::class)->create();

        $order->discounts()->attach($discounts, ['deductible_type' => Constants::DISCOUNT_NAMESPACE]);
        $order->taxes()->attach($taxes, ['deductible_type' => Constants::TAX_NAMESPACE]);

        $this->assertNotNull($order->discounts, 'Discounts are empty.');
        $this->assertCount(5, $order->discounts, 'Discounts count doesn\'t match');
        $this->assertNotNull($order->taxes, 'Taxes are empty.');
        $this->assertCount(3, $order->taxes, 'Taxes count doesn\'t match');
    }

    /**
     * Charge with order.
     *
     * @return void
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_order_charge()
    {
        $orderClass = config('nikolag.connections.square.order.namespace');

        $discountOne = factory(Discount::class)->states('AMOUNT_ONLY')->make([
            'amount' => 50,
        ]);
        $discountTwo = factory(Discount::class)->states('PERCENTAGE_ONLY')->create([
            'percentage' => 10.0,
        ]);
        $tax = factory(Tax::class)->states('INCLUSIVE')->create([
            'percentage' => 10.0,
        ]);
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->make([
            'price' => 110,
        ])->toArray();

        $product['quantity'] = 5;
        $product['discounts'] = [$discountOne->toArray()];

        $order->discounts()->attach($discountTwo->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);
        $order->taxes()->attach($tax->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::TAX_NAMESPACE]);

        $data = [
            'location_id' => env('SQUARE_LOCATION'),
            'amount' => 445,
            'source_id' => 'cnon:card-nonce-ok',
        ];

        $square = Square::setOrder($order, env('SQUARE_LOCATION'));
        $square = $square->addProduct($product);
        $square->charge($data);

        $this->assertNotNull($order->taxes, 'Taxes are empty');
        $this->assertNotNull($order->discounts, 'Discounts are empty');
        $this->assertNotNull($order->products, 'Products are empty');
        $this->assertCount(1, $square->getOrder()->taxes, 'Taxes count is not correct');
        $this->assertCount(1, $square->getOrder()->discounts, 'Discounts count is not correct');
        $this->assertCount(1, $square->getOrder()->products, 'Products count is not correct');
    }

    /**
     * Order creation without order, testing exception case.
     *
     * @return void
     */
    public function test_order_missing_order_exception()
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('$order property is missing');
        $this->expectExceptionCode(500);

        Square::setOrder(null, '');
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_order_missing_location_id_exception()
    {
        $order = factory(Order::class)->create();

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('$locationId property is missing');
        $this->expectExceptionCode(500);

        Square::setOrder($order, '');
    }

    /**
     * Order charge using trait system.
     *
     * @return void
     */
    public function test_order_trait_charge()
    {
        $order = factory(Order::class)->create();
        $merchant = factory(User::class)->create();
        $product = factory(Product::class)->create([
            'price' => 110,
        ]);
        $order->products()->attach($product);

        $response = $order->charge(110, 'cnon:card-nonce-ok', env('SQUARE_LOCATION'), $merchant);

        $this->assertTrue($response instanceof Transaction);
        $this->assertTrue($response->payment_service_type == 'square');
        $this->assertEquals($response->status, Constants::TRANSACTION_STATUS_PASSED);
        $this->assertEquals($response->amount, 110);
    }
}
