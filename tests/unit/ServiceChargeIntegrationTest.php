<?php

namespace Nikolag\Square\Tests\Unit;

use Exception;
use Illuminate\Validation\ValidationException;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\ServiceCharge;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Product as ModelsProduct;
use Square\Models\OrderServiceChargeCalculationPhase;
use Square\Models\OrderServiceChargeTreatmentType;

class ServiceChargeIntegrationTest extends TestCase
{
    /**
     * Test service charge integration with full order processing workflow.
     *
     * @return void
     */
    public function test_service_charge_integration_with_full_order_workflow(): void
    {
        // Create test data
        $order = factory(Order::class)->create();
        $product1 = factory(ModelsProduct::class)->create(['price' => 1000]); // $10.00
        $product2 = factory(Product::class)->create(['price' => 2000]); // $20.00

        // Create service charges
        $orderServiceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Handling Fee',
            'amount_money' => 5_00, // $5.00
            'calculation_phase' => OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
            'taxable' => false,
        ]);

        $productServiceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Fake Percentage Fee',
            'percentage' => 5.0,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
            'calculation_phase' => OrderServiceChargeCalculationPhase::TOTAL_PHASE,
            'taxable' => false,
        ]);

        // Create tax and discount for comprehensive testing
        $tax = factory(Tax::class)->create([
            'percentage' => 8.5,
            'type' => Constants::TAX_ADDITIVE,
        ]);

        $discount = factory(Discount::class)->create([
            'percentage' => 10.0,
            'amount' => null,
        ]);

        // Build order through Square service
        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($product1, 2) // 2 × $10.00 = $20.00
            ->addProduct($product2, 1) // 1 × $20.00 = $20.00
            ->save();

        // Attach order-level service charge
        $order->serviceCharges()->attach($orderServiceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Attach product-level service charge to first product
        $order->products->first()->pivot->serviceCharges()->attach($productServiceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Attach tax and discount at order level
        $order->taxes()->attach($tax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $order->discounts()->attach($discount->id, [
            'deductible_type' => Constants::DISCOUNT_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Refresh the order to get updated relationships
        $order->refresh();
        $order->load('products', 'serviceCharges', 'taxes', 'discounts');

        // Verify service charges are attached
        $this->assertCount(1, $order->serviceCharges, 'Order should have 1 service charge');
        $this->assertEquals('Handling Fee', $order->serviceCharges->first()->name);

        $this->assertCount(1, $order->products->first()->pivot->serviceCharges, 'First product should have 1 service charge');
        $this->assertEquals('Fake Percentage Fee', $order->products->first()->pivot->serviceCharges->first()->name);

        // Calculate expected total:
        // Products: (2 × $10.00) + (1 × $20.00) = $40.00
        // Order discount: 10% of $40.00 = -$4.00, new subtotal: $36.00
        // Product service charge: $5.00 (fixed amount on first product): $36.00 + $5.00 = $41.00
        // Tax: 8.5% of $41.00 = $3.49, new subtotal: $44.49
        // Order service charge: 5% of $44.49 = $2.22, new total: $46.71
        $actualTotal = Util::calculateTotalOrderCostByModel($order);

        $this->assertEquals(46_71, $actualTotal, 'Total calculation should include all service charges, taxes, and discounts');

        // Test building Square request with service charges
        $squareBuilder = Square::getSquareBuilder();

        // Build service charges
        $serviceCharges = $squareBuilder->buildServiceCharges($order->serviceCharges, 'USD');
        $this->assertCount(1, $serviceCharges, 'Should build 1 order-level service charge');

        // Build products with service charges
        $products = $squareBuilder->buildProducts($order->products, 'USD');
        $this->assertCount(2, $products, 'Should build 2 products');

        // Verify first product has applied service charges
        $firstProduct = $products[0];
        $appliedServiceCharges = $firstProduct->getAppliedServiceCharges();
        $this->assertCount(1, $appliedServiceCharges, 'First product should have 1 applied service charge');
    }

    /**
     * Test service charge with variable pricing support.
     *
     * @return void
     */
    public function test_service_charge_with_variable_pricing(): void
    {
        $order = factory(Order::class)->create();

        // Create product with null price (variable pricing)
        $variableProduct = factory(Product::class)->create(['price' => null]);

        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Processing Fee',
            'percentage' => 2.5,
            'amount_money' => null,
        ]);

        // Add product with custom price through Square service
        $variableProduct->price = 1500; // $15.00 custom price

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($variableProduct, 3)
            ->save();

        // Attach service charge
        $order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $order->refresh();

        // Expected: 3 × $15.00 = $45.00, service charge 2.5% = $1.13, total = $46.13 = 4613 cents
        $expectedTotal = 4613;
        $actualTotal = Util::calculateTotalOrderCostByModel($order);

        $this->assertEquals($expectedTotal, $actualTotal, 
            'Service charge should work correctly with variable pricing');
    }

    /**
     * Test service charge integration with order charging.
     *
     * @return void
     */
    public function test_service_charge_integration_with_order_charge(): void
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create(['price' => 2000]); // $20.00

        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Service Fee',
            'amount_money' => 300, // $3.00
            'amount_currency' => 'USD',
            'percentage' => null,
        ]);

        // Add a service charge to the order
        $order->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        // Build order with service charge
        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($product, 1)
            ->save();

        $order->refresh();

        // Calculate total: $20.00 + $3.00 = $23.00 = 2300 cents
        $expectedTotal = 2300;
        $actualTotal = Util::calculateTotalOrderCostByModel($order);

        $this->assertEquals($expectedTotal, $actualTotal);

        // Test charging the order
        $transaction = $square->charge([
            'amount' => $expectedTotal,
            'source_id' => 'cnon:card-nonce-ok',
            'location_id' => env('SQUARE_LOCATION'),
        ]);

        $this->assertNotNull($transaction, 'Transaction should be created successfully');
        $this->assertEquals($expectedTotal, $transaction->amount, 'Transaction amount should match calculated total');
    }

    /**
     * Test service charge integration with product charging.
     *
     * @return void
     */
    public function test_service_charge_integration_with_product_charge(): void
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create(['price' => 1500]); // $15.00
        $order->attachProduct($product, ['quantity' => 2]);

        // Create a service charge with apportioned amount calculation
        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Fixed amount service charge',
            'amount_money' => 10_00, // 10.00 USD
            'calculation_phase' => OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE,
            'taxable' => true,
            'treatment_type' => OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
        ]);

        // Add a service charge to the product
        $order->products->first()->pivot->serviceCharges()->attach($serviceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT
        ]);

        // Build order with service charge
        $square = Square::setOrder($order, env('SQUARE_LOCATION'))->save();

        // Calculate total: ($15.00 + $10.00) x 2 = $50.00
        $expectedTotal = 50_00;
        $actualTotal = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals($expectedTotal, $actualTotal);

        // Test charging the order
        $transaction = $square->charge([
            'amount' => $expectedTotal,
            'source_id' => 'cnon:card-nonce-ok',
            'location_id' => env('SQUARE_LOCATION'),
        ]);

        $this->assertNotNull($transaction, 'Transaction should be created successfully');
        $this->assertEquals($expectedTotal, $transaction->amount, 'Transaction amount should match calculated total');
    }

    /**
     * Test multiple service charges on the same order.
     *
     * @return void
     */
    public function test_multiple_service_charges_integration(): void
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create(['price' => 1000]); // $10.00

        // Create multiple service charges
        $serviceCharge1 = factory(ServiceCharge::class)->create([
            'name' => 'Service Fee',
            'percentage' => 5.0,
        ]);

        $serviceCharge2 = factory(ServiceCharge::class)->create([
            'name' => 'Processing Fee',
            'amount_money' => 100, // $1.00
            'amount_currency' => 'USD',
        ]);

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($product, 2) // 2 × $10.00 = $20.00
            ->save();

        // Attach both service charges
        $order->serviceCharges()->attach($serviceCharge1->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $order->serviceCharges()->attach($serviceCharge2->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => config('nikolag.connections.square.order.namespace'),
            'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER
        ]);

        $order->refresh();
        $order->load('serviceCharges');

        $this->assertCount(2, $order->serviceCharges, 'Order should have 2 service charges');

        // Calculate total: $20.00 + 5% ($1.00) + $1.00 = $22.00 = 2200 cents
        $expectedTotal = 2200;
        $actualTotal = Util::calculateTotalOrderCostByModel($order);

        $this->assertEquals($expectedTotal, $actualTotal, 'Multiple service charges were not calculated correctly');
    }

    /**
     * Test multiple service charges on the same order.
     *
     * @return void
     */
    public function test_total_service_charge_cannot_be_applied_to_products(): void
    {
        // Create test data
        $order = factory(Order::class)->create();
        $product1 = factory(ModelsProduct::class)->create(['price' => 1000]); // $10.00
        $product2 = factory(Product::class)->create(['price' => 2000]); // $20.00

        // Create a service charge to be applied to a product within the order
        $productServiceCharge = factory(ServiceCharge::class)->create([
            'name' => 'Handling Fee',
            'amount_money' => 1_50, // $1.50
            'amount_currency' => 'USD',
            'calculation_phase' => OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
            'treatment_type' => OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT,
        ]);

        // Build order through Square service
        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($product1, 2) // 2 × $10.00 = $20.00
            ->addProduct($product2, 1) // 1 × $20.00 = $20.00
            ->save();

        // Attach product-level service charge to first product
        $order->products->first()->pivot->serviceCharges()->attach($productServiceCharge->id, [
            'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service charge calculation phase "SUBTOTAL" cannot be applied to products in an order');

        Util::calculateTotalOrderCostByModel($order);
    }
}
