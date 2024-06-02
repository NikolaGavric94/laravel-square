<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exception;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;

class SquareServiceTest extends TestCase
{
    private TestDataHolder $data;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::make();
    }

    /**
     * Charge OK.
     *
     * @return void
     */
    public function test_square_charge_ok(): void
    {
        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertTrue($response->payment_service_type == 'square', 'Response service type is not square');
        $this->assertEquals(5000, $response->amount, 'Transaction amount is not 5000.');
        $this->assertEquals(Constants::TRANSACTION_STATUS_PASSED, $response->status, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @return void
     */
    public function test_square_charge_wrong_nonce(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Invalid source/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'not-existent-nonce', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong CVV.
     *
     * @return void
     */
    public function test_square_charge_wrong_cvv(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Postal Code.
     *
     * @return void
     */
    public function test_square_charge_wrong_postal(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_POSTAL_CODE.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-postalcode', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Expiration date.
     *
     * @return void
     */
    public function test_square_charge_wrong_expiration_date(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge declined.
     *
     * @return void
     */
    public function test_square_charge_declined(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with already used nonce.
     *
     * @return void
     */
    public function test_square_charge_used_nonce(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with non-existant currency.
     *
     * @return void
     */
    public function test_square_charge_non_existant_currency(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/This merchant can only process payments in USD, but amount was provided in XXX/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION'), 'currency' => 'XXX']);
    }

    /**
     * Charge without location.
     *
     * @return void
     */
    public function test_square_charge_missing_location_id(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required field \'location_id\' is missing');
        $this->expectExceptionCode(500);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok']);
    }

    /**
     * Order creation through facade.
     *
     * @return void
     */
    public function test_square_order_make(): void
    {
        $this->data->order->discounts()->attach($this->data->discount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($this->data->tax->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($this->data->product->id, ['quantity' => 5]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'));

        $this->assertEquals($square->getOrder(), $this->data->order, 'Orders are not the same');
    }

    /**
     * Add product for order.
     *
     * @return void
     */
    public function test_square_order_add_product(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product, 1)->addProduct($product2, 2)->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');
    }

    /**
     * Add product and delivery fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment(
                [
                    'type'             => Constants::FULFILLMENT_TYPE_DELIVERY,
                    'state'            => 'PROPOSED',
                    'delivery_details' => [
                        'scheduled_type' => 'ASAP',
                        'placed_at'      => now(),
                        'carrier'        => 'USPS',
                    ]
                ],
            )
            ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and delivery fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_fulfillment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithDeliveryDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof DeliveryDetails,
            'Fulfillment details are not DeliveryDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment(
                [
                    'type'           => Constants::FULFILLMENT_TYPE_PICKUP,
                    'state'          => 'PROPOSED',
                    'pickup_details' => [
                        'scheduled_type' => 'ASAP',
                        'placed_at'      => now()->format(Constants::DATE_FORMAT)
                    ]
                ],
            )
            ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        // Make sure the fulfillment exists on the order
        $this->assertCount(1, $square->getOrder()->fulfillments, 'Fulfillment is missing from order');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithPickupDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof PickupDetails,
            'Fulfillment details are not PickupDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment with curbside pickup details for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment_width_curbside_pickup_details(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment(
                [
                    'type'           => Constants::FULFILLMENT_TYPE_PICKUP,
                    'state'          => 'PROPOSED',
                    'pickup_details' => [
                        'scheduled_type'          => 'ASAP',
                        'placed_at'               => now(),
                        'is_curbside_pickup'      => true,
                        'curbside_pickup_details' => [
                            'curbside_details' => 'Mazda CX5, Black, License Plate: 1234567',
                            'buyer_arrived_at' => null,
                        ]
                    ]
                ],
            )
            ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        // Make sure the fulfillment exists on the order
        $this->assertCount(1, $square->getOrder()->fulfillments, 'Fulfillment is missing from order');

        // Make sure the fulfillment details are PickupDetails
        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof PickupDetails,
            'Fulfillment details are not PickupDetails'
        );

        // Make sure the curbside pickup data flag is set to true
        $this->assertTrue(
            !empty($square->getOrder()->fulfillments->first()->fulfillmentDetails->is_curbside_pickup),
            'Curbside pickup flag is not set to true'
        );

        // Make sure the curbside data is present
        $this->assertNotNull(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details,
            'Curbside pickup details are not set'
        );

        $this->assertNull(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details->buyer_arrived_at,
            'Buyer arrived at is not null'
        );

        $this->assertEquals(
            'Mazda CX5, Black, License Plate: 1234567',
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details->curbside_details,
            'Curbside details are not the same'
        );
    }

    /**
     * Add product and shipment fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_shipment_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment(
                [
                    'type'             => Constants::FULFILLMENT_TYPE_SHIPMENT,
                    'state'            => 'PROPOSED',
                    'shipment_details' => [
                        'scheduled_type' => 'ASAP',
                        'placed_at'      => now(),
                    ]
                ],
                Constants::FULFILLMENT_TYPE_SHIPMENT
            )
            ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and shipment fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_shipment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithShipmentDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof ShipmentDetails,
            'Fulfillment details are not ShipmentDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Makes sure the Square Order throws an error when a fulfillment is present but no recipient is set.
     *
     * @return void
     */
    public function test_square_order_fulfillment_with_no_recipient(): void
    {
        $product2 = factory(Product::class)->create();

        // Set up the error expectations
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required fields are missing');
        $this->expectExceptionCode(500);

        Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment(
                [
                    'type'             => Constants::FULFILLMENT_TYPE_DELIVERY,
                    'state'            => 'PROPOSED',
                    'delivery_details' => [
                        'scheduled_type' => 'ASAP',
                        'placed_at'      => now(),
                        'carrier'        => 'USPS',
                    ]
                ],
            )
            // ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray()) // Commented out to test the error
            ->save();
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_transaction_list(): void
    {
        $array = [
            'location_id' => env('SQUARE_LOCATION'),
        ];

        $transactions = Square::payments($array);

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\Square\Models\ListPaymentsResponse', $transactions);
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_locations_list(): void
    {
        $transactions = Square::locations();

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\Square\Models\ListLocationsResponse', $transactions);
    }

    /**
     * Save an order through facade.
     *
     * @return void
     */
    public function test_square_order_facade_save(): void
    {
        $order = $this->data->order->toArray();
        $product = $this->data->product->toArray();
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
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_order_facade_save_and_charge(): void
    {
        $orderArr = $this->data->order->toArray();
        $product = $this->data->product->toArray();
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
     * Test we throw proper exception and message
     * when the customer has invalid phone number.
     *
     * @return void
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_invalid_customer_phone_number(): void
    {
        try {
            $this->data->customer->phone = 'bad phone number';
            Square::setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product)->charge(
                ['amount' => 0, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
            );
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
            $this->assertMatchesRegularExpression('/Expected phone_number to be a valid phone number/i', $e->getPrevious()->getMessage());
            $this->assertEquals(400, $e->getPrevious()->getCode());
        }
    }

    /**
     * Test we throw proper exception and message
     * when the customer has invalid email address.
     *
     * @return void
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_invalid_customer_email_address(): void
    {
        try {
            $this->data->customer->email = 'bad email address';
            Square::setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product)->charge(
                ['amount' => 0, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
            );
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
            $this->assertMatchesRegularExpression('/Expected email_address to be a valid email address/i', $e->getPrevious()->getMessage());
            $this->assertEquals(400, $e->getPrevious()->getCode());
        }
    }

    /**
     * Test all in one as arrays.
     *
     * @return void
     */
    public function test_square_array_all(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make'));

        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];

        $transaction = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($orderArr, env('SQUARE_LOCATION'))
            ->addProduct($productArr)
            ->charge(['amount' => 850, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
    }

    /**
     * Test all in one as models.
     *
     * @return void
     */
    public function test_square_model_all(): void
    {
        $this->data = TestDataHolder::create();
        extract($this->data->modify());

        $this->data->order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxInclusive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($product);

        $this->data->order->products->get(0)->pivot->discounts()->attach($productDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);

        $transaction = Square::setMerchant($this->data->merchant)->setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->charge(
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
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function test_square_total_calculation(): void
    {
        $this->data = TestDataHolder::create();
        extract($this->data->modify());

        $this->data->order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->discounts()->attach($orderDiscountFixed->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxAdditive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxInclusive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($product);

        $square = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->save();

        $calculatedCost = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals(707, $calculatedCost);
    }

    /**
     * Test all in one as arrays with addition of scope.
     *
     * @return void
     */
    public function test_square_array_all_scopes(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make', taxAddFac: 'make'));
        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];
        $productArr['taxes'] = [$taxAdditive->toArray()];

        $transaction = Square::setMerchant($this->data->merchant)->setCustomer($this->data->customer)->setOrder($orderArr, env('SQUARE_LOCATION'))->addProduct($productArr)
            ->charge([
                'amount' => 935,
                'source_id' => 'cnon:card-nonce-ok',
                'location_id' => env('SQUARE_LOCATION')
            ]);

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
        $this->assertContains(Product::find(1)->id, $transaction->order->products->pluck('id'), 'Product is not part of the order.');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_PRODUCT,
            $transaction->order->discounts->where('name', $productDiscount->name)->first()->pivot->scope, 'Discount scope is not \'LINE_ITEM\'');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_PRODUCT,
            $transaction->order->taxes->where('name', $taxAdditive->name)->first()->pivot->scope, 'Tax scope is not \'LINE_ITEM\'');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_ORDER,
            $transaction->order->discounts->where('name', $orderDiscount->name)->first()->pivot->scope, 'Discount scope is not \'ORDER\'');
    }
}
