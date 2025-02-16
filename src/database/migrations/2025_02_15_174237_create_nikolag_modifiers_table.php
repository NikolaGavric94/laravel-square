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
        Schema::create('nikolag_modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('square_catalog_object_id');
            $table->integer('ordinal')->nullable();
            $table->enum('selection_type', [
                CatalogModifierListSelectionType::SINGLE,
                CatalogModifierListSelectionType::MULTIPLE
            ])->default(CatalogModifierListSelectionType::SINGLE);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_product_modifiers');
    }
};
