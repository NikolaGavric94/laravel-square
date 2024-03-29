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
    public function up()
    {
        Schema::create('nikolag_fulfillment_order', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fulfillment_id');
            $table->string('fulfillment_type', 25);
            $table->string('order_id', 25);
        });

        Schema::table('nikolag_fulfillment_order', function (Blueprint $table) {
            $table->foreign('fulfillment_id', 'fulfill_id')->references('id')->on('nikolag_fulfillments');
            $table->unique(['fulfillment_id', 'order_id', 'fulfillment_type'], 'fulfillid_ordid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nikolag_product_order');
    }
};
