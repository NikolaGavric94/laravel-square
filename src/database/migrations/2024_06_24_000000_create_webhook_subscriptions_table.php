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
        Schema::create('nikolag_webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('square_id')->unique();
            $table->string('name');
            $table->string('notification_url');
            $table->json('event_types');
            $table->string('api_version');
            $table->string('signature_key');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['is_enabled']);
            $table->index(['square_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_webhook_subscriptions');
    }
};
