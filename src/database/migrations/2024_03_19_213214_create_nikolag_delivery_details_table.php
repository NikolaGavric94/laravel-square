<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nikolag_delivery_details', function (Blueprint $table) {
            $table->id();
            $table->string('fulfillment_uid', 60)->nullable()->unique();
            $table->string('recipient_id', 191)->nullable();
            $table->enum('schedule_type', ['SCHEDULED', 'ASAP']);
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('deliver_at')->nullable();
            $table->string('prep_time_duration')->nullable();
            $table->string('delivery_window_duration')->nullable();
            $table->string('note', 550)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason', 100)->nullable();
            $table->timestamp('courier_picked_up_at')->nullable();
            $table->string('courier_pickup_window_duration')->nullable();
            $table->boolean('is_no_contact_delivery')->default(false);
            $table->string('dropoff_notes', 550)->nullable();
            $table->string('courier_provider_name', 255)->nullable();
            $table->string('courier_support_phone_number', 17)->nullable();
            $table->string('square_delivery_id', 50)->nullable();
            $table->string('external_delivery_id', 50)->nullable();
            $table->boolean('managed_delivery')->default(false);
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_delivery_details', function (Blueprint $table) {
            $table->index('recipient_id');
            $table->index('fulfillment_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_delivery_details');
    }
};
