<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\CatalogModifierListSelectionType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nikolag_modifier_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('square_catalog_object_id');
            $table->unsignedBigInteger('price_money_amount')->nullable();
            $table->string('price_money_currency', 3)->nullable();
            $table->foreignId('modifier_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_modifier_options');
    }
};
