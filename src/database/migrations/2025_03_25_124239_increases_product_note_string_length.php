<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\CatalogModifierListSelectionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('nikolag_products', function (Blueprint $table) {
            // Increase the note column length
            $table->string('note', 2000)->nullable()->change();
            // Allow pricing to be nullable for variable pricing
            $table->float('price')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('nikolag_products', function (Blueprint $table) {
            $table->string('note', 50)->nullable()->change();
        });
    }
};
