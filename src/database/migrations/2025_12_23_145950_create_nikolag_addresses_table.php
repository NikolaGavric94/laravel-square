<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create nikolag_addresses table for polymorphic address storage.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nikolag_addresses', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship fields (nullable for flexibility)
            $table->nullableMorphs('addressable');

            // Address fields following Square's Address object structure
            $table->string('address_line_1', 500)->nullable();
            $table->string('address_line_2', 500)->nullable();
            $table->string('address_line_3', 500)->nullable();
            $table->string('locality', 255)->nullable()->comment('City');
            $table->string('administrative_district_level_1', 255)->nullable()->comment('State/Province');
            $table->string('administrative_district_level_2', 255)->nullable()->comment('County/District');
            $table->string('administrative_district_level_3', 255)->nullable()->comment('Sub-district');
            $table->string('sublocality', 255)->nullable()->comment('Neighborhood');
            $table->string('sublocality_2', 255)->nullable();
            $table->string('sublocality_3', 255)->nullable();
            $table->string('postal_code', 20)->nullable()->comment('ZIP/Postal code');
            $table->string('country', 2)->nullable()->comment('ISO 3166-1-alpha-2 country code');

            $table->timestamps();

            // Indexes for common queries
            $table->index('country');
            $table->index('postal_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nikolag_addresses');
    }
};
