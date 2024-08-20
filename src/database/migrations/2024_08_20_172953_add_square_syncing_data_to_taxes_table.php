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
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            $table->string('square_catalog_object_id', 192)->nullable();
            $table->dateTime('square_created_at')->nullable();
            $table->dateTime('square_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            $table->dropColumn('square_catalog_object_id');
        });
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            $table->dropColumn('square_created_at');
        });
        Schema::table('nikolag_taxes', function (Blueprint $table) {
            $table->dropColumn('square_updated_at');
        });
    }
};
