<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\LocationStatus;
use Square\Models\LocationType;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('nikolag_locations', function (Blueprint $table) {
            $table->id();
            $table->string('square_id', 32);
            $table->string('name', 255)->nullable();
            $table->json('address')->nullable();
            $table->string('timezone', 30)->nullable();
            $table->json('capabilities', 30)->nullable();
            $table->enum('status', [LocationStatus::ACTIVE, LocationStatus::INACTIVE])->default(LocationStatus::ACTIVE);
            $table->dateTime('square_created_at')->nullable();
            $table->string('merchant_id', 32)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('language_code', 5)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('phone_number', 17)->nullable();
            $table->string('business_name', 255)->nullable();
            $table->enum('type', [LocationType::PHYSICAL, LocationType::MOBILE])->default(LocationType::PHYSICAL);
            $table->string('website_url', 255)->nullable();
            $table->json('business_hours')->nullable();
            $table->string('business_email', 255)->nullable();
            $table->string('description', 1024)->nullable();
            $table->string('twitter_username', 15)->nullable();
            $table->string('instagram_username', 30)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('coordinates')->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->string('pos_background_url', 255)->nullable();
            $table->string('mcc', 4)->nullable();
            $table->string('full_format_logo_url')->nullable();
            // $table->json('tax_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        //
    }
};
