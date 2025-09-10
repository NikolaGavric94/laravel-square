<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds a unique constraint to prevent duplicate modifiers
     * per order product, ensuring local data consistency with Square's API
     * validation which rejects orders with duplicate catalog object IDs.
     */
    public function up(): void
    {
        // Add unique constraint to ensure no duplicate modifiers per order product
        Schema::table('nikolag_product_order_modifier', function (Blueprint $table) {
            // Create a unique constraint on the combination of:
            // - order_product_id: identifies the specific order product
            // - modifiable_id: identifies the specific modifier/modifier option
            // - modifiable_type: identifies whether it's a Modifier or ModifierOption
            $table->unique(
                ['order_product_id', 'modifiable_id', 'modifiable_type'],
                'nikolag_product_order_modifier_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nikolag_product_order_modifier', function (Blueprint $table) {
            $table->dropUnique('nikolag_product_order_modifier_unique');
        });
    }
};
