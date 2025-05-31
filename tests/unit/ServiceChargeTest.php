<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\ServiceCharge;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class ServiceChargeTest extends TestCase
{
    /**
     * Service charge creation.
     *
     * @return void
     */
    public function test_service_charge_make(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'amount_money' => 1000,
        ]);

        $this->assertNotNull($serviceCharge, 'Service charge is null.');
    }

    /**
     * Service charge persisting.
     *
     * @return void
     */
    public function test_service_charge_create(): void
    {
        $name = $this->faker->name;

        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => $name,
            'amount_money' => 1000,
        ]);

        $this->assertDatabaseHas('nikolag_service_charges', [
            'name' => $name,
        ]);
    }

    /**
     * Check order persisting with service charges.
     *
     * @return void
     */
    public function test_service_charge_create_with_orders(): void
    {
        $name = $this->faker->name;
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => $name,
            'amount_money' => 1000,
        ]);

        $serviceCharge->orders()->attach($order1, ['featurable_type' => Order::class, 'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $serviceCharge->orders()->attach($order2, ['featurable_type' => Order::class, 'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);

        $this->assertCount(2, $serviceCharge->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $serviceCharge->orders);
    }

    /**
     * Check product persisting with service charges.
     *
     * @return void
     */
    public function test_service_charge_create_with_products(): void
    {
        $name = $this->faker->name;
        $product1 = factory(OrderProductPivot::class)->create();
        $product2 = factory(OrderProductPivot::class)->create();

        $serviceCharge = factory(ServiceCharge::class)->create([
            'name' => $name,
            'amount_money' => 1000,
        ]);

        $serviceCharge->products()->attach($product1, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
        $serviceCharge->products()->attach($product2, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::SERVICE_CHARGE_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);

        $this->assertCount(2, $serviceCharge->products);
        $this->assertContainsOnlyInstancesOf(Constants::ORDER_PRODUCT_NAMESPACE, $serviceCharge->products);
    }

    /**
     * Test service charge percentage calculation.
     *
     * @return void
     */
    public function test_service_charge_percentage(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'percentage' => 10.0,
            'amount_money' => null,
        ]);

        $this->assertEquals(10.0, $serviceCharge->percentage);
        $this->assertNull($serviceCharge->amount_money);
    }

    /**
     * Test service charge fixed amount calculation.
     *
     * @return void
     */
    public function test_service_charge_fixed_amount(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'amount_money' => 500,
            'amount_currency' => 'USD',
            'percentage' => null,
        ]);

        $this->assertEquals(500, $serviceCharge->amount_money);
        $this->assertEquals('USD', $serviceCharge->amount_currency);
        $this->assertNull($serviceCharge->percentage);
    }

    /**
     * Test service charge taxable property.
     *
     * @return void
     */
    public function test_service_charge_taxable(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'amount_money' => 1000,
            'taxable' => true,
        ]);

        $this->assertTrue($serviceCharge->taxable);
    }

    /**
     * Test service charge error handling with invalid data.
     *
     * @return void
     */
    public function test_service_charge_error_handling(): void
    {
        // Test creating service charge without amount or percentage
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Service charge must have either percentage or amount_money set');

        factory(ServiceCharge::class)->create([
            'amount_money' => null,
            'percentage' => null,
        ]);
    }

    /**
     * Test service charge with both amount and percentage (should fail).
     *
     * @return void
     */
    public function test_service_charge_both_amount_and_percentage_error(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Service charge cannot have percentage while amount_money is set.');

        factory(ServiceCharge::class)->create([
            'amount_money' => 100,
            'percentage' => 5.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Service charge cannot have amount_money while percentage is set.');

        factory(ServiceCharge::class)->create([
            'amount_money' => 100,
            'percentage' => 5.0,
        ]);
    }

    /**
     * Test service charge can have a tax.
     *
     * @return void
     */
    public function test_service_charge_can_have_tax(): void
    {
        // Create a new tax of 8%
        $tax = factory(Tax::class)->create([
            'percentage' => 8.0,
            'type' => Constants::TAX_ADDITIVE,
        ]);

        $serviceCharge = factory(ServiceCharge::class)->create([
            'amount_money' => 1000,
        ]);

        $serviceCharge->taxes()->attach($tax->id, [
            'deductible_type' => Constants::TAX_NAMESPACE,
            'featurable_type' => Constants::SERVICE_CHARGE_NAMESPACE,
            'scope' => Constants::DEDUCTIBLE_SCOPE_SERVICE_CHARGE
        ]);

        $this->assertCount(1, $serviceCharge->taxes);
        $this->assertContainsOnlyInstancesOf(Tax::class, $serviceCharge->taxes);
    }
}
