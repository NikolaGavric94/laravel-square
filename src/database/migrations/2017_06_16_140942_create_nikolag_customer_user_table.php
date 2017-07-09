<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNikolagCustomerUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nikolag_customer_user', function(Blueprint $table) {
            $table->string('owner_id');
            $table->integer('customer_id')->unsigned();
        });

        Schema::table('nikolag_customer_user', function(Blueprint $table) {
            $table->unique(['owner_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nikolag_customer_user');
    }
}
