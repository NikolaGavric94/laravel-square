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
        Schema::table('nikolag_webhook_subscriptions', function (Blueprint $table) {
            $table->text('signature_key')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nikolag_webhook_subscriptions', function (Blueprint $table) {
            $table->string('signature_key')->change();
        });
    }
};
