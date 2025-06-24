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
        Schema::create('nikolag_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('square_event_id')->unique();
            $table->string('event_type');
            $table->json('event_data');
            $table->timestamp('event_time');
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['square_event_id']);
            $table->index(['event_type']);
            $table->index(['status']);
            $table->index(['event_time']);
            $table->index(['subscription_id']);

            // Foreign key to webhook subscriptions
            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('nikolag_webhook_subscriptions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_webhook_events');
    }
};