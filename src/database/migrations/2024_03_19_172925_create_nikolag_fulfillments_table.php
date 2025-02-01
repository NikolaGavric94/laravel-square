<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\FulfillmentState;
use Square\Models\FulfillmentType;

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
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('type', [FulfillmentType::PICKUP, FulfillmentType::SHIPMENT, FulfillmentType::DELIVERY]);
            $table->enum('state', [
                FulfillmentState::PROPOSED,
                FulfillmentState::RESERVED,
                FulfillmentState::PREPARED,
                FulfillmentState::COMPLETED,
                FulfillmentState::CANCELED,
                FulfillmentState::FAILED
            ])->nullable();
            $table->string('uid', 60)->nullable();
            // Adds fulfillment_details_id, fulfillment_details_type columns and index
            $table->morphs('fulfillment_details');
            $table->timestamps();

            // Add indexes - these will be frequently queried
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
