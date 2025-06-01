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
        Schema::create('nikolag_order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 60)->nullable();
            $table->string('source_order_id')->nullable();

            // Since we only need to read OrderReturns and cannot make new returns, just shove
            // everything else into a json field.
            $table->json('data')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('uid');
            $table->index('source_order_id');
            $table->index(['source_order_id', 'uid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_order_returns');
    }
};
