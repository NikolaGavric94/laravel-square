<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->string('type');
            $table->string('state');
            $table->string('uid');
            $table->timestamps();
        });

        // Add indexes
        Schema::table('nikolag_fulfillments', function (Blueprint $table) {
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
