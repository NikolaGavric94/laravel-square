<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNikolagCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nikolag_customers', function(Blueprint $table) {
            $table->increments('id');
            $table->integer('square_id')->unsigned();
            $table->string('first_name')->nullable()->default(null);
            $table->string('last_name')->nullable()->default(null);
            $table->string('company_name')->nullable()->default(null);
            $table->string('nickname')->nullable()->default(null);
            $table->string('email')->unique();
            $table->string('phone')->nullable()->default(null);
            $table->longText('note')->nullable()->default(null);
            $table->string('owner_id')->nullable()->default(null);
            $table->timestamps();
        });

        Schema::table('nikolag_customers', function(Blueprint $table) {
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nikolag_customers');
    }
}
