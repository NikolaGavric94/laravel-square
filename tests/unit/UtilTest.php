<?php

namespace Nikolag\Square\Tests\Unit;

use Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\ServiceCharge;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\OrderServiceChargeCalculationPhase;
use Square\Models\OrderServiceChargeTreatmentType;
use Square\Models\TaxCalculationPhase;

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
     * Test variable pricing support - product with null price but price in order
     *
     * @return void
     */
    public function test_calculate_total_order_with_variable_pricing_and_modifier(): void
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

        // Set variable price for the product
        $chocolateChipCookie->price += 1_00; // Increase the price by $1.00 to simulate variable pricing

        // Create a new order
        // 5 regular chocolate chip cookies with "fancy frosting"
        // $6.50/ea ($6.00/ea + $0.50 modifier) - $32.50 total)
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($chocolateChipCookie, 5, modifiers: [$fancyFrostingOption])
            ->save();

        // The expected total is $32.50.
        $this->assertEquals(32_50, Util::calculateTotalOrderCostByModel($square->getOrder()));
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

    /**
     * Test service charge calculation with percentage.
     *
     * @return void
     */
    public function test_apportioned_amount_service_charge_calculation(): void
    {
        $this->set_up_service_charges_order();

        // Create a service charge with apportioned amount calculation
        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Apportioned amount service charge',
            'amount_money' => 10_00, // 10.00 USD
            'calculation_phase' => OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE,
            'taxable' => true,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
        ]);

        // Add the service charge to the order
        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: $116.00, Service charge $10.00, Total: $126.00
        $this->assertEquals(126_00, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with percentage.
     *
     * @return void
     */
    public function test_apportioned_percentage_service_charge_calculation(): void
    {
        $this->set_up_service_charges_order();

        // Create a service charge with apportioned amount calculation
        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Apportioned percentage service charge',
            'percentage' => 10.0, // 10%
            'calculation_phase' => OrderServiceChargeCalculationPhase::APPORTIONED_PERCENTAGE_PHASE,
            'taxable' => true,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
        ]);

        // Add the service charge to the order
        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: $116.00, Service charge $11.60, Total: $127.60
        $this->assertEquals(127_60, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with tax and discount.
     *
     * @return void
     */
    public function test_service_charge_with_tax_and_discount_calculation(): void
    {
        $tax = factory(Tax::class)->create([
            'percentage' => 10.0,
            'type' => Constants::TAX_ADDITIVE,
        ]);

        $discount = factory(Discount::class)->create([
            'percentage' => 10.0,
            'amount' => null,
        ]);

        $serviceCharge = factory(ServiceCharge::class)->create([
            'percentage' => 5.0,
            'amount_money' => null,
        ]);

        $this->data->order->save();

        // Set a specific price for predictable test results
        $this->data->product->price = 1000;
        $this->data->product->save();

        $this->data->order->taxes()->attach($tax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->discounts()->attach($discount->id, [
            'deductible_type' => Constants::DISCOUNT_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->attachProduct($this->data->product);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base: 1000, Discount 10%: -100 = 900, Tax 10%: +90 = 990, Service charge 5%: +49.5 = 1039.5 (rounded to 1040)
        $this->assertEquals(1040, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with percentage.
     *
     * @return void
     */
    public function test_apportioned_service_charge_taxes_calculation(): void
    {
        $this->set_up_service_charges_order();

        // Create a new tax of 8%
        $tax = factory(Tax::class)->create([
            'percentage' => 8.0,
            'type' => Constants::TAX_ADDITIVE,
        ]);

        // Create a service charge with apportioned amount calculation
        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Fixed amount service charge',
            'amount_money' => 10_00, // 10.00 USD
            'calculation_phase' => OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE,
            'taxable' => true,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
        ]);

        // Apply the tax to the service charge
        $serviceCharge->taxes()->attach($tax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Add the service charge to the order
        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT
        ]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: $116.00, Service charge $10.00 x 6 = $60.00, Total: $176.00
        $this->assertEquals(176_00, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with percentage.
     *
     * @return void
     */
    public function test_service_charge_taxes_calculation(): void
    {
        $this->set_up_service_charges_order();

        // Create a new tax of 8%
        $tax = factory(Tax::class)->create([
            'percentage' => 8.0,
            'type' => Constants::TAX_ADDITIVE,
        ]);

        // Create a percentage-based service charge with a subtotal calculation phase
        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Service charge with taxes',
            'amount_money' => 10_00, // 10.00 USD
            'calculation_phase' => OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
            'taxable' => true,
            'treatment_type' => OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT,
        ]);

        // Apply the tax to the service charge
        $serviceCharge->taxes()->attach($tax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_SERVICE_CHARGE
        ]);

        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $square = Square::setOrder($this->data->order->refresh(), env('SQUARE_LOCATION'))->save();

        // Base cost: $116.00, Service charge $10.00, Tax on service charge $0.80 Total: $126.80
        $this->assertEquals(126_80, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with percentage.
     *
     * @return void
     */
    public function test_service_charge_percentage_calculation(): void
    {
        $this->set_up_service_charges_order();

        // Create a percentage-based service charge with a subtotal calculation phase
        $serviceCharge = factory(ServiceCharge::class)->create([
            'percentage' => 1.5,
            'calculation_phase' => OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
        ]);

        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: $116.00, Service charge $1.74, Total: $117.74
        $this->assertEquals(117_74, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test service charge calculation with fixed amount.
     *
     * @return void
     */
    public function test_service_charge_fixed_amount_calculation(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'amount_money' => 200,
            'amount_currency' => 'USD',
            'percentage' => null,
        ]);

        $this->data->order->save();

        // Set a specific price for predictable test results
        $this->data->product->price = 1000;
        $this->data->product->save();

        $this->data->order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->attachProduct($this->data->product);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: 1000, Service charge fixed: 200, Total: 1200
        $this->assertEquals(1200, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Test product-level service charge calculation.
     *
     * @return void
     */
    public function test_product_service_charge_calculation(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'percentage' => 15.0,
            'calculation_phase' => OrderServiceChargeCalculationPhase::TOTAL_PHASE,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
            'taxable' => false,
        ]);

        $this->data->order->save();

        // Set a specific price for predictable test results
        $this->data->product->price = 1000;
        $this->data->product->save();

        $this->data->order->attachProduct($this->data->product);

        // Attach service charge at product level
        $this->data->order->products->first()->pivot->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Base cost: 1000, Product service charge 15%: 150, Total: 1150
        $this->assertEquals(1150, Util::calculateTotalOrderCostByModel($square->getOrder()));
    }

    /**
     * Adds a specific set of products for use when calculating totals related to service charges tests.
     *
     * This method is used to ensure that the products are set up correctly and allows for a 1-to-1 comparison of the
     * examples found on Square's documentation.
     *
     * @see https://developer.squareup.com/docs/orders-api/service-charges
     *
     * @return void
     */
    private function set_up_service_charges_order(): void
    {
        // Save the order first
        $this->data->order->save();

        // Create three products to distribute the service charge
        $product1 = factory(Product::class)->create(['price' => 15_00]); // 15.00 USD
        $product2 = factory(Product::class)->create(['price' => 50_00]); // 50.00 USD
        $product3 = factory(Product::class)->create(['price' => 12_00]); // 12.00 USD

        // Add the products to the order
        $this->data->order->attachProduct($product1, ['quantity' => 2]); // 2 x 15.00 USD = 30.00 USD
        $this->data->order->attachProduct($product2, ['quantity' => 1]); // 1 x 50.00 USD = 50.00 USD
        $this->data->order->attachProduct($product3, ['quantity' => 3]); // 3 x 12.00 USD = 36.00 USD
    }

    /**
     * Test tax calculation with subtotal phase.
     *
     * @return void
     */
    public function test_tax_subtotal_phase_calculation(): void
    {
        // Create a subtotal phase tax
        $subtotalTax = factory(Tax::class)->create([
            'name' => 'Sales Tax (Subtotal)',
            'percentage' => 10,
            'type' => Constants::TAX_ADDITIVE,
            'calculation_phase' => TaxCalculationPhase::TAX_SUBTOTAL_PHASE,
        ]);

        $this->data->order->save();
        $this->data->product->price = 100_00; // $100.00
        $this->data->product->save();

        // Attach subtotal tax to the order
        $this->data->order->taxes()->attach($subtotalTax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $this->data->order->attachProduct($this->data->product);
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Expected calculation:
        // Base: $100.00
        // Subtotal Tax (10%): $10.00 → Subtotal: $110.00
        $expectedTotal = 110_00;
        $actualTotal = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals($expectedTotal, $actualTotal);
    }

    /**
     * Test legacy tax behavior (no calculation_phase specified).
     *
     * @return void
     */
    public function test_tax_total_phase_calculation(): void
    {
        // Create a tax without calculation_phase (should default to subtotal behavior)
        $totalTax = factory(Tax::class)->create([
            'name' => 'Legacy Tax',
            'percentage' => 7.0,
            'type' => Constants::TAX_ADDITIVE,
            // No calculation_phase specified
        ]);

        $this->data->order->save();
        $this->data->product->price = 100_00; // $100.00
        $this->data->product->save();

        // Attach tax to order
        $this->data->order->taxes()->attach($totalTax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $this->data->order->attachProduct($this->data->product);
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Expected calculation (should behave like subtotal phase):
        // Base: $100.00
        // Total tax (7%): $7.00 → Total: $107.00
        $expectedTotal = 107_00;
        $actualTotal = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals($expectedTotal, $actualTotal);
    }

    /**
     * Test comprehensive tax calculation with both phases and service charges.
     *
     * @return void
     */
    public function test_comprehensive_tax_calculation_phases(): void
    {
        // Create both subtotal and total phase taxes
        $subtotalTax = factory(Tax::class)->create([
            'name' => 'State Tax (Subtotal)',
            'percentage' => 5.0,
            'type' => Constants::TAX_ADDITIVE,
            'calculation_phase' => TaxCalculationPhase::TAX_SUBTOTAL_PHASE,
        ]);

        $totalTax = factory(Tax::class)->create([
            'name' => 'City Tax (Total)',
            'percentage' => 3.0,
            'type' => Constants::TAX_ADDITIVE,
            'calculation_phase' => TaxCalculationPhase::TAX_TOTAL_PHASE,
        ]);

        // Create service charges for both phases
        $subtotalServiceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Processing Fee',
            'amount_money' => 5_00, // $5.00
            'calculation_phase' => OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
            'taxable' => false,
        ]);

        $totalServiceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Convenience Fee',
            'percentage' => 2.0,
            'calculation_phase' => OrderServiceChargeCalculationPhase::TOTAL_PHASE,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
            'taxable' => false,
        ]);

        $this->data->order->save();
        $this->data->product->price = 100_00; // $100.00
        $this->data->product->save();

        // Attach both taxes
        $this->data->order->taxes()->attach($subtotalTax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->taxes()->attach($totalTax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Attach both service charges
        $this->data->order->serviceCharges()->attach($subtotalServiceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);
        $this->data->order->serviceCharges()->attach($totalServiceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $this->data->order->attachProduct($this->data->product);
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->save();

        // Expected calculation:
        // Step 1: Base amount = $100.00
        // Step 2: Subtotal service charge = $5.00 → Subtotal = $105.00
        // Step 3: Subtotal tax (5%) = $5.25 → After subtotal tax = $110.25
        // Step 4: Total service charge (2%) = $2.21 → Before total tax = $112.46
        // Step 5: Total tax (3%) = $3.37 → Final total = $115.83
        $expectedTotal = 115_83;
        $actualTotal = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals($expectedTotal, $actualTotal,
            'Tax calculation phases did not produce expected result. ' .
            'Expected: $115.83, Actual: $' . number_format($actualTotal / 100, 2)
        );
    }
}
