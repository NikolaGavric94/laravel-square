<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\TaxCalculationPhase;
use Square\Models\TaxInclusionType;

/**
 * Migration to add Square CatalogTax attributes to taxes table.
 */
class AddSquareCatalogTaxAttributesToTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            // Change percentage to nullable
            $table->float('percentage')->nullable()->change();
            // Square CatalogTax attributes
            $table->unsignedBigInteger('amount_money')->nullable();
            $table->string('amount_currency', 3)->nullable();
            $table->enum('calculation_phase', [
                TaxCalculationPhase::TAX_SUBTOTAL_PHASE,
                TaxCalculationPhase::TAX_TOTAL_PHASE
            ])->default(TaxCalculationPhase::TAX_TOTAL_PHASE);
            $table->enum('inclusion_type', [
                TaxInclusionType::ADDITIVE,
                TaxInclusionType::INCLUSIVE
            ])->default(TaxInclusionType::ADDITIVE);
            $table->boolean('applies_to_custom_amounts')->default(false);
            $table->boolean('enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_phase',
                'inclusion_type',
                'applies_to_custom_amounts',
                'enabled',
            ]);
        });
    }
};
