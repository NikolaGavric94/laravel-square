<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\CatalogModifierListSelectionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('nikolag_product_order', function (Blueprint $table) {
            // Add the modifier_option_id column
            $table->foreignId('modifier_id')->nullable()->constrained('nikolag_modifiers')->onDelete('cascade');
            $table->foreignId('modifier_option_id')->nullable()->constrained('nikolag_modifier_options')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('nikolag_product_order', function (Blueprint $table) {
            // Drop the modifier_option_id column
            $table->dropColumn('modifier_option_id');
        });
    }
};
