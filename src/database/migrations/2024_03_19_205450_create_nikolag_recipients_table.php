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
        Schema::create('nikolag_recipients', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->nullable();

            $table->string('display_name', 255)->nullable();
            $table->string('square_customer_id', 191)->nullable();
            $table->string('email_address', 255)->nullable();
            $table->string('phone_number', 17)->nullable();
            $table->json('address')->nullable();

            // One-to-one relationship with fulfillment - recipient belongs to fulfillment
            $table->foreignId('fulfillment_id')->unique()->constrained('nikolag_fulfillments')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_recipients');
    }
};
