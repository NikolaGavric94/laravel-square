<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Nikolag\Square\Models\ServiceCharge;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class ServiceChargeValidationTest extends TestCase
{
    /**
     * Test that subtotal phase cannot be applied to products.
     *
     * @return void
     */
    public function test_subtotal_phase_cannot_be_applied_to_products(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->make([
            'percentage' => 5.0,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_LINE_ITEM,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Subtotal phase service charges cannot be applied at the product (line-item) level');

        $serviceCharge->validateProductLevelApplication();
    }

    /**
     * Test that total phase cannot be taxable.
     *
     * @return void
     */
    public function test_total_phase_cannot_be_taxable(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Total phase service charges cannot be taxable');

        factory(ServiceCharge::class)->create([
            'percentage' => 5.0,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_TOTAL,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_LINE_ITEM,
            'taxable' => true, // This should trigger validation error
        ]);
    }

    /**
     * Test that apportioned amount phase cannot use line item treatment.
     *
     * @return void
     */
    public function test_apportioned_amount_phase_cannot_use_line_item_treatment(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Apportioned amount phase cannot be used with line item treatment');

        factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_LINE_ITEM,
            'amount_money' => 500,
        ]);
    }

    /**
     * Test that apportioned amount phase must have amount, not percentage.
     *
     * @return void
     */
    public function test_apportioned_amount_phase_must_have_amount(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Apportioned amount phase service charges must have a dollar amount, not a percentage');

        factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED,
            'percentage' => 5.0,
        ]);
    }

    /**
     * Test that apportioned percentage phase cannot use line item treatment.
     *
     * @return void
     */
    public function test_apportioned_percentage_phase_cannot_use_line_item_treatment(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Apportioned percentage phase cannot be used with line item treatment');

        factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_LINE_ITEM,
            'percentage' => 5.0,
        ]);
    }

    /**
     * Test that apportioned percentage phase must have percentage, not amount.
     *
     * @return void
     */
    public function test_apportioned_percentage_phase_must_have_percentage(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Apportioned percentage phase service charges must have a percentage, not a dollar amount');

        factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED,
            'amount_money' => 500,
        ]);
    }

    /**
     * Test valid apportioned amount phase service charge.
     *
     * @return void
     */
    public function test_valid_apportioned_amount_phase(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED,
            'amount_money' => 500,
        ]);

        $this->assertTrue($serviceCharge->canBeAppliedToProduct());
        $this->assertTrue($serviceCharge->canBeAppliedToOrder());
        $this->assertDatabaseHas('nikolag_service_charges', [
            'id' => $serviceCharge->id,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_AMOUNT,
        ]);
    }

    /**
     * Test valid apportioned percentage phase service charge.
     *
     * @return void
     */
    public function test_valid_apportioned_percentage_phase(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED,
            'percentage' => 5.0,
        ]);

        $this->assertTrue($serviceCharge->canBeAppliedToProduct());
        $this->assertTrue($serviceCharge->canBeAppliedToOrder());
        $this->assertDatabaseHas('nikolag_service_charges', [
            'id' => $serviceCharge->id,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_APPORTIONED_PERCENTAGE,
        ]);
    }

    /**
     * Test valid subtotal phase service charge (order level only).
     *
     * @return void
     */
    public function test_valid_subtotal_phase(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL,
            'percentage' => 5.0,
        ]);

        $this->assertFalse($serviceCharge->canBeAppliedToProduct());
        $this->assertTrue($serviceCharge->canBeAppliedToOrder());
        $this->assertDatabaseHas('nikolag_service_charges', [
            'id' => $serviceCharge->id,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL,
        ]);
    }

    /**
     * Test valid total phase service charge (order level only, non-taxable).
     *
     * @return void
     */
    public function test_valid_total_phase(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->create([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_TOTAL,
            'treatment_type' => Constants::SERVICE_CHARGE_TREATMENT_APPORTIONED,
            'percentage' => 5.0,
            'taxable' => false,
        ]);

        $this->assertFalse($serviceCharge->canBeAppliedToProduct());
        $this->assertTrue($serviceCharge->canBeAppliedToOrder());
        $this->assertDatabaseHas('nikolag_service_charges', [
            'id' => $serviceCharge->id,
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_TOTAL,
        ]);
    }

    /**
     * Test static validation method.
     *
     * @return void
     */
    public function test_static_validation_before_product_attachment(): void
    {
        $serviceCharge = factory(ServiceCharge::class)->make([
            'calculation_phase' => Constants::SERVICE_CHARGE_CALCULATION_PHASE_SUBTOTAL,
            'percentage' => 5.0,
        ]);

        $this->expectException(ValidationException::class);
        ServiceCharge::validateBeforeProductAttachment($serviceCharge);
    }
}
