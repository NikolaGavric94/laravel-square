<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\CatalogModifierListSelectionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nikolag_product_order_modifier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained('nikolag_modifiers')->onDelete('cascade');
            $table->foreignId('order_product_pivot_id')->constrained('nikolag_product_order')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('nikolag_product_order_modifier');
    }
};
