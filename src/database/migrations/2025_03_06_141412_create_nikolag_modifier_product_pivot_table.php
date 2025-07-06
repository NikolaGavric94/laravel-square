<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('modifier_id');
            $table->timestamps();
        });

        // Fix column type mismatch before adding foreign key constraints
        // Change product_id to match nikolag_products.id (int unsigned)
        Schema::table('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->unsignedInteger('product_id')->change();
        });

        // Add foreign key constraints for nikolag_modifier_product_pivot table
        // When a product is deleted, all modifier-product relationships are also deleted
        Schema::table('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('nikolag_products')
                ->onDelete('cascade');
        });

        // When a modifier is deleted, all modifier-product relationships are also deleted
        Schema::table('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->foreign('modifier_id')
                ->references('id')
                ->on('nikolag_modifiers')
                ->onDelete('cascade');
        });

        // Add a unique constraint to prevent duplicate entries
        Schema::table('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->unique(['product_id', 'modifier_id'], 'modifier_product_unique');
        });

        // Add an index for faster lookups
        Schema::table('nikolag_modifier_product_pivot', function (Blueprint $table) {
            $table->index(['product_id', 'modifier_id'], 'modifier_product_index');
        });

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('nikolag_modifier_product_pivot');
    }
};
