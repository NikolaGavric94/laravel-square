<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nikolag\Square\Utils\Constants;

class CreateNikolagTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nikolag_transactions', function(Blueprint $table) {
            $table->increments('id');
            $table->enum('status', [
                Constants::TRANSACTION_STATUS_OPENED, 
                Constants::TRANSACTION_STATUS_PASSED, 
                Constants::TRANSACTION_STATUS_FAILED
            ]);
            $table->string('amount');
            $table->integer('customer_id')->unsigned()->nullable()->default(null);
            $table->string('merchant_id')->nullable()->default(null);
            $table->string('order_id')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('nikolag_transactions', function(Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('nikolag_customers')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nikolag_transactions');
    }
}
