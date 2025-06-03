<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\OrderServiceChargeCalculationPhase;
use Square\Models\OrderServiceChargeTreatmentType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nikolag_service_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->unsignedBigInteger('amount_money')->nullable();
            $table->string('amount_currency', 3)->nullable();
            $table->float('percentage')->nullable();
            $table->enum('calculation_phase', [
                OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE,
                OrderServiceChargeCalculationPhase::TOTAL_PHASE,
                OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE,
                OrderServiceChargeCalculationPhase::APPORTIONED_PERCENTAGE_PHASE
            ])->default('SUBTOTAL_PHASE');
            $table->boolean('taxable')->default(false);
            $table->enum('treatment_type', [
                OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT,
                OrderServiceChargeTreatmentType::APPORTIONED_TREATMENT,
            ])->default('LINE_ITEM_TREATMENT');
            $table->string('reference_id', 255)->nullable();
            $table->string('square_catalog_object_id', 192)->nullable();
            $table->dateTime('square_created_at')->nullable();
            $table->dateTime('square_updated_at')->nullable();
            $table->timestamps();

            // Add indexes for frequently queried columns
            $table->index('square_catalog_object_id');
            $table->index('calculation_phase');
            $table->index('treatment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_service_charges');
    }
};
