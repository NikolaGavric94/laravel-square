<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exception;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Tests\Traits\MocksSquareConfigDependency;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\CatalogObject;
use Square\Models\CatalogObjectType;
use Square\Models\RetrieveOrderResponse;

class SquareServiceTest extends TestCase
{
    use MocksSquareConfigDependency;

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
     * Save an order through facade, inputting a simple example (the one currently used in the wiki).
     *
     * @return void
     */
    public function test_square_order_facade_save_simple_array(): void
    {
        $products = [
            [
                'name' => 'Shirt',
                'variation_name' => 'Large white',
                'note' => 'This note can have maximum of 50 characters.',
                'price' => 440.99,
                'quantity' => 2,
                'reference_id' => '5', //An optional ID to associate the product with an entity ID in your own table
            ],
            [
                'name' => 'Shirt',
                'variation_name' => 'Mid-size yellow',
                'note' => 'This note can have maximum of 50 characters.',
                'quantity' => 1,
                'price' => 118.02,
            ],
        ];

        $order = [
            'products' => $products,
        ];

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))->save();

        $this->assertCount(1, Order::all(), 'There is not enough orders');
        $this->assertEquals($square->getOrder()->id, Order::find(1)->id, 'Order is not the same as in charge');
        $this->assertNull($square->getOrder()->payment_service_id, 'Payment service identifier is null');
    }

    /**
     * Save an order through facade with conflicting location ids.
     *
     * @return void
     */
    public function test_square_order_facade_save_location_conflict(): void
    {
        $order = $this->data->order->toArray();
        $product = $this->data->product->toArray();
        $product['quantity'] = 1;
        $order['products'] = [$product];

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Invalid order data');
        $this->expectExceptionCode(500);

        // Save an order with a non-existing location id - while the location id is set in the order data, causign a
        // conflict
        Square::setOrder($order, 123456789)->save();
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
                'location_id' => env('SQUARE_LOCATION'),
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

    /**
     * Tests retrieving catalog information.
     *
     * @return void
     */
    public function test_square_list_catalog(): void
    {
        $catalog = Square::listCatalog();

        $this->assertNotNull($catalog);
        $this->assertIsArray($catalog);
        foreach ($catalog as $item) {
            $this->assertInstanceOf('\Square\Models\CatalogObject', $item);
        }
    }

    /**
     * Ensures filtering the catalog by type is supported
     *
     * @return void
     */
    public function test_square_list_catalog_by_type(): void
    {
        $catalogItems = Square::listCatalog([CatalogObjectType::ITEM]);

        $this->assertNotNull($catalogItems);
        $this->assertIsArray($catalogItems);
        foreach ($catalogItems as $item) {
            $this->assertInstanceOf(CatalogObject::class, $item);
            $this->assertEquals('ITEM', $item->getType());
        }
    }

    /**
     * Ensures an exception is thrown when retrieving non-standard catalog information.
     *
     * @return void
     */
    public function test_square_list_catalog_unsupported_type(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INVALID_REQUEST_ERROR: Unknown object type "UNSUPPORTED_ITEM"');
        $this->expectExceptionCode(400);

        Square::listCatalog(['unsupported_item']);
    }

    /**
     * Test retrieving an order successfully.
     *
     * @return void
     */
    public function test_square_retrieve_order_success(): void
    {
        $orderId = 'test-square-order-123';
        $locationId = 'test-location-456';

        // Mock successful retrieve order response
        $this->mockRetrieveOrderSuccess([
            'id' => $orderId,
            'locationId' => $locationId,
            'state' => 'OPEN',
            'version' => 1,
            'createdAt' => '2023-10-18T10:00:00Z',
            'updatedAt' => '2023-10-18T10:00:00Z'
        ]);

        $retrievedOrder = Square::retrieveOrder($orderId);

        $this->assertNotNull($retrievedOrder);
        $this->assertInstanceOf(RetrieveOrderResponse::class, $retrievedOrder);
        $this->assertNotNull($retrievedOrder->getOrder());
        $this->assertEquals($orderId, $retrievedOrder->getOrder()->getId());
        $this->assertEquals($locationId, $retrievedOrder->getOrder()->getLocationId());
        $this->assertEquals('OPEN', $retrievedOrder->getOrder()->getState());
    }

    /**
     * Test retrieving a non-existent order using mocking.
     *
     * @return void
     */
    public function test_square_retrieve_order_not_found(): void
    {
        // Mock error response for non-existent order
        $this->mockRetrieveOrderError('Order not found', 404);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        Square::retrieveOrder('non-existent-order-id');
    }
}
