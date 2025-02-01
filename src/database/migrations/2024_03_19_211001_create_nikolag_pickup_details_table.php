<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\OrderFulfillmentPickupDetailsScheduleType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nikolag_pickup_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nikolag_fulfillment_id')->constrained('nikolag_fulfillments')->cascadeOnDelete();

            // Square Order Fulfillment Pickup Details
            $table->string('fulfillment_uid', 60)->nullable()->unique();
            $table->string('recipient_id', 191)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('auto_complete_duration')->nullable();
            $table->enum('schedule_type', [
                OrderFulfillmentPickupDetailsScheduleType::SCHEDULED,
                OrderFulfillmentPickupDetailsScheduleType::ASAP
            ]);
            $table->timestamp('pickup_at')->nullable();
            $table->string('pickup_window_duration')->nullable();
            $table->string('prep_time_duration')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason', 100)->nullable();
            $table->boolean('is_curbside_pickup')->default(false);
            $table->json('curbside_pickup_details')->nullable();
            $table->timestamp('buyer_arrived_at')->nullable();
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_pickup_details', function (Blueprint $table) {
            $table->index('recipient_id');
            $table->index('fulfillment_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_pickup_details');
    }
};
