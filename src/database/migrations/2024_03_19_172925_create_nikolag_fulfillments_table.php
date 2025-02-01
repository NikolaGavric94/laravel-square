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
        Schema::create('nikolag_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->enum('type', ['PICKUP', 'SHIPMENT', 'DELIVERY']);
            $table->enum('state', ['PROPOSED', 'RESERVED', 'PREPARED', 'COMPLETED', 'CANCELED', 'FAILED'])->nullable();
            $table->string('uid', 60)->nullable();
            // Adds fulfillment_details_id and fulfillment_details_type columns and index
            $table->morphs('fulfillment_details');
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_fulfillments', function (Blueprint $table) {
            $table->index('type');
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_fulfillments');
    }
};
