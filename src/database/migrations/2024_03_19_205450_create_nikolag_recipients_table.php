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
            $table->foreignID('customer_id')->nullable()->constrained('customers');

            $table->string('display_name', 255)->nullable();
            $table->string('square_customer_id', 191)->nullable();
            $table->string('email_address', 255)->nullable();
            $table->string('phone_number', 17)->nullable();
            $table->json('address')->nullable();
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_recipients', function (Blueprint $table) {
            $table->index('customer_id');
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
