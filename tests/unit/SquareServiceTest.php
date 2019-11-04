<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Builders\OrderBuilder;
use Nikolag\Square\Exceptions\InvalidSquareCurrencyException;
use Nikolag\Square\Exceptions\InvalidSquareCvvException;
use Nikolag\Square\Exceptions\InvalidSquareExpirationDateException;
use Nikolag\Square\Exceptions\InvalidSquareNonceException;
use Nikolag\Square\Exceptions\InvalidSquareZipcodeException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;

class SquareServiceTest extends TestCase
{
    /**
     * Charge OK.
     *
     * @return void
     */
    public function test_square_charge_ok()
    {
        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertTrue($response->payment_service_type == 'square', 'Response service type is not square');
        $this->assertEquals($response->amount, 5000, 'Transaction amount is not 5000.');
        $this->assertEquals($response->status, Constants::TRANSACTION_STATUS_PASSED, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @return void
     */
    public function test_square_charge_wrong_nonce()
    {
        $this->expectException(InvalidSquareNonceException::class);
        $this->expectExceptionCode(404);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'not-existent-nonce', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong CVV.
     *
     * @return void
     */
    public function test_square_charge_wrong_cvv()
    {
        $this->expectException(InvalidSquareCvvException::class);
        $this->expectExceptionCode(402);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Postal Code.
     *
     * @return void
     */
    public function test_square_charge_wrong_postal()
    {
        $this->expectException(InvalidSquareZipcodeException::class);
        $this->expectExceptionCode(402);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-postalcode', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Expiration date.
     *
     * @return void
     */
    public function test_square_charge_wrong_expiration_date()
    {
        $this->expectException(InvalidSquareExpirationDateException::class);
        $this->expectExceptionCode(400);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge declined.
     *
     * @return void
     */
    public function test_square_charge_declined()
    {
        $this->expectException(InvalidSquareExpirationDateException::class);
        $this->expectExceptionCode(400);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with already used nonce.
     *
     * @return void
     */
    public function test_square_charge_used_nonce()
    {
        $this->expectException(InvalidSquareCvvException::class);
        $this->expectExceptionCode(402);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with non-existant currency.
     *
     * @return void
     */
    public function test_square_charge_non_existant_currency()
    {
        $this->expectException(InvalidSquareCurrencyException::class);
        $this->expectExceptionCode(400);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION'), 'currency' => 'XXX']);
    }

    /**
     * Charge without location.
     *
     * @return void
     */
    public function test_square_charge_missing_location_id()
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required field \'location_id\' is missing');
        $this->expectExceptionCode(500);

        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok']);
    }

    /**
     * Order creation through facade.
     *
     * @return void
     */
    public function test_square_order_make()
    {
        $discount = factory(Discount::class)->states('AMOUNT_ONLY')->create();
        $tax = factory(Tax::class)->create();
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();

        $order->discounts()->attach($discount, ['deductible_type' => Constants::DISCOUNT_NAMESPACE]);
        $order->taxes()->attach($tax, ['deductible_type' => Constants::TAX_NAMESPACE]);
        $order->products()->attach($product->id, ['quantity' => 5]);

        $square = Square::setOrder($order, env('SQUARE_LOCATION'));

        $this->assertEquals($square->getOrder(), $order, 'Orders are not the same');
    }

    /**
     * Add product for order.
     *
     * @return void
     */
    public function test_square_order_add_product()
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))->addProduct($product, 1)->addProduct($product2, 2)->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_transaction_list()
    {
        $array = [
            'location_id' => env('SQUARE_LOCATION'),
        ];

        $transactions = Square::payments($array);

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\SquareConnect\Model\ListPaymentsResponse', $transactions);
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_locations_list()
    {
        $transactions = Square::locations();

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\SquareConnect\Model\ListLocationsResponse', $transactions);
    }

    /**
     * Save an order through facade.
     *
     * @return void
     */
    public function test_square_order_facade_save()
    {
        $order = factory(Order::class)->make();
        $product = factory(Product::class)->make();
        $order = $order->toArray();
        $product = $product->toArray();
        $product['quantity'] = 1;
        $order['products'] = [$product];

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))->save();

        $this->assertCount(1, Order::all(), 'There is not enough orders');
        $this->assertEquals($square->getOrder()->id, Order::find(1)->id, 'Order is not the same as in charge');
        $this->assertNull($square->getOrder()->payment_service_id, 'Payment service identifier is null');
    }

    /**
     * Save and charge an order through facade.
     *
     * @return void
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_order_facade_save_and_charge()
    {
        $order = factory(Order::class)->make();
        $product = factory(Product::class)->make();
        $orderArr = $order->toArray();
        $product = $product->toArray();
        $product['quantity'] = 1;
        $orderArr['products'] = [$product];

        $square = Square::setOrder($orderArr, env('SQUARE_LOCATION'))->save();
        $transaction = $square->charge(
            ['amount' => $product['price'], 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
        );
        //Load order again for equals check
        $transaction->load('order');

        $this->assertCount(1, Order::all(), 'Order count is not correct');
        $this->assertCount(1, $square->getOrder()->products, 'Products count is not correct');
        $this->assertCount(1, Transaction::all(), 'There is not enough transactions');
        $this->assertNotNull($square->getOrder()->payment_service_id, 'Payment service identifier is null');
        $this->assertNotNull($transaction->order, 'Order is not connected with Transaction');
        $this->assertEquals(Order::find(1), $transaction->order, 'Order is not the same');
    }

    /**
     * Test all in one as arrays.
     *
     * @return void
     */
    public function test_square_array_all()
    {
        $merchant = factory(User::class)->create();
        $customer = factory(Customer::class)->make();
        $order = factory(Order::class)->make();
        $product = factory(Product::class)->make([
            'price' => 1000,
        ]);
        $productDiscount = factory(Discount::class)->states('AMOUNT_ONLY')->make([
            'amount' => 50,
        ]);
        $orderDiscount = factory(Discount::class)->states('PERCENTAGE_ONLY')->create([
            'percentage' => 10.0,
        ]);
        $tax = factory(Tax::class)->states('INCLUSIVE')->make([
            'percentage' => 10.0,
        ]);
        $orderArr = $order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];

        $transaction = Square::setMerchant($merchant)->setCustomer($customer)->setOrder($orderArr, env('SQUARE_LOCATION'))->addProduct($productArr)->charge(
            ['amount' => 850, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
        );

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
    }

    /**
     * Test all in one as models.
     *
     * @return void
     */
    public function test_square_model_all()
    {
        $merchant = factory(User::class)->create();
        $customer = factory(Customer::class)->create();
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create([
            'price' => 1000,
        ]);
        $productDiscount = factory(Discount::class)->states('AMOUNT_ONLY')->create([
            'amount' => 50,
        ]);
        $orderDiscount = factory(Discount::class)->states('PERCENTAGE_ONLY')->create([
            'percentage' => 10.0,
        ]);
        $tax = factory(Tax::class)->states('INCLUSIVE')->create([
            'percentage' => 10.0,
        ]);

        $order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace')]);
        $order->taxes()->attach($tax->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace')]);
        $order->products()->attach($product);

        $order->products->get(0)->pivot->discounts()->attach($productDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE]);

        $transaction = Square::setMerchant($merchant)->setCustomer($customer)->setOrder($order, env('SQUARE_LOCATION'))->charge(
            ['amount' => 850, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
        );

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
    }

    /**
     * Test all in one as models.
     *
     * @return void
     * @throws \Nikolag\Square\Exceptions\InvalidSquareOrderException
     * @throws \Nikolag\Square\Exceptions\MissingPropertyException
     */
    public function test_square_total_calculation()
    {
        $orderBuilder = new OrderBuilder();
        $merchant = factory(User::class)->create();
        $customer = factory(Customer::class)->create();
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create([
            'price' => 1000,
        ]);
        $productDiscount = factory(Discount::class)->states('AMOUNT_ONLY')->create([
            'amount' => 50,
        ]);
        $orderDiscount = factory(Discount::class)->states('PERCENTAGE_ONLY')->create([
            'percentage' => 10.0,
        ]);
        $tax = factory(Tax::class)->states('ADDITIVE')->create([
            'percentage' => 10.0,
        ]);

        $order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace')]);
        $order->taxes()->attach($tax->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace')]);
        $order->products()->attach($product);

        $order->products->get(0)->pivot->discounts()->attach($productDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE]);

        $square = Square::setMerchant($merchant)->setCustomer($customer)->setOrder($order, env('SQUARE_LOCATION'));
        $orderCopy = $orderBuilder->buildOrderCopyFromModel($square->getOrder());
        $calculatedCost = Util::calculateTotalOrderCost($orderCopy);

        $this->assertEquals(950, $calculatedCost);
    }
}
