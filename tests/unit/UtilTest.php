<?php

namespace Nikolag\Square\Tests\Unit;

use Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;

class UtilTest extends TestCase
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Product
     */
    protected $product;

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
     * Performs assertions shared by all tests of a test case.
     *
     * This method is called between setUp() and test.
     */
    public function assertPreConditions(): void
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
        $this->product = factory(Product::class)->make([
            'price' => 110,
        ])->toArray();

        $this->product['quantity'] = 5;
        $this->product['discounts'] = [$discountOne->toArray()];

        $order->discounts()->attach($discountTwo->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $order->taxes()->attach($tax->id, ['featurable_type' => $orderClass, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);

        $this->order = $order;
    }

    /**
     * Test if the order doesnt have the product.
     *
     * @return void
     */
    public function test_order_doesnt_have_product(): void
    {
        $found = Util::hasProduct($this->order->products, $this->product);

        $this->assertFalse($found, 'Util::hasProduct has returned true');
        $this->assertEmpty($this->order->products, 'Products attribute is not empty.');
    }

    /**
     * Test if the total calculation is done right.
     *
     * @return void
     */
    public function test_calculate_total_order_cost(): void
    {
        $square = Square::setOrder($this->order, env('SQUARE_LOCATION'))
            ->addProduct($this->product)
            ->save();
        $expected = 445;
        $actual = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals($expected, $actual, 'Util::calculateTotalOrderCost didn\'t calculate properly.');
    }

    /**
     * Test if the total calculation is done right.
     *
     * @return void
     */
    public function test_calculate_total_order_with_product_discount(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make', taxAddFac: 'make'));
        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];
        $productArr['taxes'] = [$taxAdditive->toArray()];

        // Create a square order with all sorts of discounts and taxes.
        $square = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($orderArr, env('SQUARE_LOCATION'))
            ->addProduct($productArr)
            ->save();

        // The expected total is 935.
        $this->assertEquals(935, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test if the total calculation is done right.
     *
     * @return void
     */
    public function test_calculate_total_order_with_product_modifier(): void
    {
        // Sync the modifiers and products
        if (Modifier::count() === 0 || Product::count() === 0) {
            Square::syncModifiers();
            Square::syncProducts();
        }

        // Get the product and modifier
        $chocolateChipCookie = Product::where('name', 'Chocolate Chip Cookie')->first();
        $frostingModifierList = $chocolateChipCookie->modifiers->where('name', 'Cookie Frosting')->first();
        $fancyFrostingOption = $frostingModifierList->options->where('name', 'Fancy Frosting')->first();

        // Create a new order
        // 5 regular chocolate chip cookies with "fancy frosting"
        // $5.50/ea ($5.00/ea + $0.50 modifier) - $27.50 total)
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($chocolateChipCookie, 5, modifiers: [$fancyFrostingOption])
            ->save();

        // The expected total is $27.50.
        $this->assertEquals(2750, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test if the total calculation is done right.
     *
     * @return void
     */
    public function test_calculate_total_order_with_order_discount(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make', taxAddFac: 'make'));
        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $orderArr['taxes'] = [$taxAdditive->toArray()];
        $productArr = $product->toArray();

        // Create a square order with all sorts of discounts and taxes.
        $square = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($orderArr, env('SQUARE_LOCATION'))
            ->addProduct($productArr)
            ->save();

        // The expected total is 990.
        $this->assertEquals(990, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test missing attributes for the calculation.
     *
     * @return void
     */
    public function test_calculate_total_order_cost_missing_data(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Total cost cannot be calculated without products.');

        // Run the calculation with missing products
        Util::calculateTotalOrderCostByModel($this->order);
    }

    /**
     * Test if the order does have the product when added through square service.
     *
     * @return void
     */
    public function test_order_does_have_product_with_square_service(): void
    {
        $square = Square::setOrder($this->order, env('SQUARE_LOCATION'))
            ->addProduct($this->product)
            ->save();
        $found = Util::hasProduct($square->getOrder()->products, $this->product);

        $this->assertTrue($found, 'Util::hasProduct has returned false');
        $this->assertNotEmpty($square->getOrder()->products, 'Products attribute is empty.');
        $this->assertDatabaseHas('nikolag_products', [
            'name' => $this->product['name'],
        ]);
    }

    /**
     * Test variable pricing support - product with null price but price in order
     *
     * @return void
     */
    public function test_calculate_total_order_with_mock_variable_pricing(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make', taxAddFac: 'make'));

        // Create a product with null price (variable pricing)
        $variablePriceProduct = factory(Product::class)->make([
            'price' => null, // No price in the product record
        ])->toArray();

        // But provide price in the order
        $variablePriceProduct['quantity'] = 3;
        $variablePriceProduct['price'] = 750; // Price only in the order

        // Create a square order
        $square = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($this->data->order->toArray(), env('SQUARE_LOCATION'))
            ->addProduct($variablePriceProduct)
            ->save();

        // Expected total is 3 * 750 = 2250
        $this->assertEquals(2250, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test if the method uid returns exactly 60 characters.
     *
     * @return void
     */
    public function test_uid_returns_exactly_thirty_characters(): void
    {
        $actual = Util::uid();

        $this->assertEquals(60, strlen($actual), 'Util::uid has not returned 60 characters');
    }

    /**
     * Test if the method uid returns whatever is provided.
     *
     * @return void
     */
    public function test_uid_returns_x_characters(): void
    {
        $random = rand(1, 50);
        $actual = Util::uid($random);

        $this->assertEquals($random * 2, strlen($actual), 'Util::uid has not returned '.($random * 2).' characters');
    }

    /**
     * Customer persisting.
     *
     * @return void
     */
    public function test_customer_create(): void
    {
        $email = $this->faker->email;

        $customer = factory(Customer::class)->create([
            'email' => $email,
        ]);

        $this->assertDatabaseHas('nikolag_customers', [
            'email' => $email,
        ]);
    }

    /**
     * Listing transcations for customers.
     *
     * @return void
     */
    public function test_customers_have_transactions(): void
    {
        $customers = factory(Customer::class, 25)
            ->create()
            ->each(function ($customer) {
                $customer->transactions()->save(factory(Transaction::class)->create());
            });
        $customer = $customers->random();

        $this->assertCount(25, Transaction::all(), 'Number of transactions is not 25.');
        $this->assertNotEmpty($customer->transactions, 'Transactions are not empty.');
        $this->assertCount(1, $customer->transactions, 'Transactions count tied with Customer is not 1.');
    }

    /**
     * List transactions.
     *
     * @return void
     */
    public function test_customer_transaction_list(): void
    {
        $customer = factory(Customer::class)->create();
        $collection = $customer->transactions;

        $this->assertEmpty($collection, 'List of customers is not empty.');
        $this->assertTrue($collection->isEmpty(), 'List of customers is not empty.');
    }

    /**
     * Count scoped queries for different
     * transaction statuses.
     *
     * @return void
     */
    public function test_customer_transactions_statuses(): void
    {
        $user = factory(User::class)->create();
        $openedTransactions = factory(Transaction::class, 5)->states('OPENED')->create();
        $failedTransactions = factory(Transaction::class, 2)->states('FAILED')->create();
        $passedTransactions = factory(Transaction::class)->states('PASSED')->create();

        $user->transactions()->saveMany($openedTransactions);
        $user->transactions()->saveMany($failedTransactions);
        $user->transactions()->save($passedTransactions);

        $this->assertCount(5, $user->openedTransactions, 'Opened transactions count tied with User is not 5.');
        $this->assertCount(2, $user->failedTransactions, 'Failed transactions count tied with User is not 2.');
        $this->assertCount(1, $user->passedTransactions, 'Passed transactions count tied with User is not 1.');
        $this->assertCount(8, $user->transactions, 'Transactions count tied with User is not 8.');
    }
}
