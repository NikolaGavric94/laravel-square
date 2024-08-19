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
        Schema::create('nikolag_shipment_details', function (Blueprint $table) {
            $table->id();
            $table->string('fulfillment_uid', 255)->nullable()->unique();
            $table->string('recipient_id')->nullable();
            $table->string('carrier', 50)->nullable();
            $table->string('shipping_note', 500)->nullable();
            $table->string('shipping_type', 50)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 2000)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('packaged_at')->nullable();
            $table->timestamp('expected_shipped_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancel_reason', 100)->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 100)->nullable();
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_shipment_details', function (Blueprint $table) {
            $table->index('recipient_id');
            $table->index('fulfillment_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_shipment_details');
    }
};
