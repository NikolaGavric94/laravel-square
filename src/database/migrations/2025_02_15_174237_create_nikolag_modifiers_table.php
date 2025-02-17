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
            $table->string('name', 255);
            $table->string('internal_name', 512)->nullable();
            $table->string('square_catalog_object_id');
            $table->integer('ordinal')->nullable();
            $table->enum('selection_type', [
                CatalogModifierListSelectionType::SINGLE,
                CatalogModifierListSelectionType::MULTIPLE
            ])->default(CatalogModifierListSelectionType::SINGLE);
            $table->enum('modifier_type', [
                'LIST',
                'TEXT',
            ])->default('LIST');
            $table->integer('max_length')->nullable();
            $table->boolean('is_text_required')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nikolag_modifiers');
    }
};
