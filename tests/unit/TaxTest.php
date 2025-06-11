<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Square\Models\TaxCalculationPhase;
use Square\Models\TaxInclusionType;

class TaxTest extends TestCase
{
    /**
     * Tax creation.
     *
     * @return void
     */
    public function test_tax_make(): void
    {
        $tax = factory(Tax::class)->create();

        $this->assertNotNull($tax, 'Tax is null.');
    }

    /**
     * Tax persisting.
     *
     * @return void
     */
    public function test_tax_create(): void
    {
        $name = $this->faker->name;

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_taxes', [
            'name' => $name,
        ]);
    }

    /**
     * Check order persisting with taxes.
     *
     * @return void
     */
    public function test_tax_create_with_orders(): void
    {
        $name = $this->faker->name;
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $tax->orders()->attach($order1, ['featurable_type' => Order::class, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $tax->orders()->attach($order2, ['featurable_type' => Order::class, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);

        $this->assertCount(2, $tax->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $tax->orders);
    }

    /**
     * Check product persisting with taxes.
     *
     * @return void
     */
    public function test_tax_create_with_products(): void
    {
        $name = $this->faker->name;
        $product1 = factory(OrderProductPivot::class)->create();
        $product2 = factory(OrderProductPivot::class)->create();

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $tax->products()->attach($product1, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
        $tax->products()->attach($product2, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);

        $this->assertCount(2, $tax->products);
        $this->assertContainsOnlyInstancesOf(Constants::ORDER_PRODUCT_NAMESPACE, $tax->products);
    }

    /**
     * Test Square CatalogTax attributes creation.
     *
     * @return void
     */
    public function testTaxCreateWithSquareAttributes(): void
    {
        $tax = factory(Tax::class)->create(
            [
                'name' => 'Sales Tax',
                'percentage' => 8.25,
                'calculation_phase' => TaxCalculationPhase::TAX_SUBTOTAL_PHASE,
                'inclusion_type' => TaxInclusionType::ADDITIVE,
                'applies_to_custom_amounts' => true,
                'enabled' => true,
                'square_catalog_object_id' => 'TAX_OBJECT_123'
            ]
        );

        $this->assertDatabaseHas(
            'nikolag_taxes',
            [
                'name' => 'Sales Tax',
                'percentage' => 8.25,
                'calculation_phase' => TaxCalculationPhase::TAX_SUBTOTAL_PHASE,
                'inclusion_type' => TaxInclusionType::ADDITIVE,
                'applies_to_custom_amounts' => true,
                'enabled' => true,
                'square_catalog_object_id' => 'TAX_OBJECT_123'
            ]
        );
    }

    /**
     * Test tax with all Square CatalogTax attributes filled.
     *
     * @return void
     */
    public function testTaxCompleteSquareCatalogTax(): void
    {
        $tax = factory(Tax::class)->create(
            [
                'name' => 'Complete Tax',
                'type' => 'STATE',
                'percentage' => 10.0,
                'calculation_phase' => TaxCalculationPhase::TAX_TOTAL_PHASE,
                'inclusion_type' => TaxInclusionType::INCLUSIVE,
                'applies_to_custom_amounts' => false,
                'enabled' => true,
                'square_catalog_object_id' => 'TAX_CAT_OBJ_789'
            ]
        );

        // Test all attributes are correctly stored
        $this->assertEquals('Complete Tax', $tax->name);
        $this->assertEquals('STATE', $tax->type);
        $this->assertEquals(10.0, $tax->percentage);
        $this->assertEquals(TaxCalculationPhase::TAX_TOTAL_PHASE, $tax->calculation_phase);
        $this->assertEquals(TaxInclusionType::INCLUSIVE, $tax->inclusion_type);
        $this->assertFalse($tax->applies_to_custom_amounts);
        $this->assertTrue($tax->enabled);
        $this->assertEquals('TAX_CAT_OBJ_789', $tax->square_catalog_object_id);
    }

    /**
     * Test tax error handling with invalid data.
     *
     * @return void
     */
    public function test_tax_error_handling(): void
    {
        // Test creating tax without amount or percentage
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tax must have either percentage or amount_money set');

        factory(Tax::class)->create([
            'amount_money' => null,
            'percentage' => null,
        ]);
    }

    /**
     * Test tax with both amount and percentage (should fail).
     *
     * @return void
     */
    public function test_tax_both_amount_and_percentage_error(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tax cannot have both percentage and amount_money set. Please specify only one.');

        factory(Tax::class)->create([
            'amount_money' => 100,
            'percentage' => 5.0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tax cannot have amount_money while percentage is set.');

        factory(Tax::class)->create([
            'amount_money' => 100,
            'percentage' => 5.0,
        ]);
    }
}
