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
        Schema::table('nikolag_product_order', function (Blueprint $table) {
            $table->string('square_uid', 60)->unique()->nullable();
            $table->unsignedBigInteger('price_money_amount');
            $table->string('price_money_currency', 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('order_product_pivot', function (Blueprint $table) {
            //
        });
    }
};
