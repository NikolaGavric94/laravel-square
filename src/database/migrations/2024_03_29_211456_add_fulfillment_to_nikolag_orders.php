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
        Schema::table('nikolag_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('fulfillment_id')->nullable();
            $table->string('fulfillment_type', 25)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nikolag_orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_id');
        });
        Schema::table('nikolag_orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });
    }
};
