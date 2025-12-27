<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Square\Models\CustomerCreationSource;

/**
 * Add Square Customer API fields to nikolag_customers table.
 *
 * This migration adds comprehensive Square Customer fields including:
 * - Birthday and reference_id for enhanced customer profiles
 * - Version tracking for optimistic locking during updates
 * - Separate address columns for better queryability
 * - JSON fields for complex objects (preferences, groups, segments, tax IDs)
 *
 * Enables full parity with Square's Customer object and better sync consistency.
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
        Schema::table('nikolag_customers', function (Blueprint $table) {
            // Profile fields
            $table->date('birthday')->nullable();
            $table->string('reference_id')->nullable();
            $table->enum('creation_source', [
                CustomerCreationSource::OTHER,
                CustomerCreationSource::APPOINTMENTS,
                CustomerCreationSource::COUPON,
                CustomerCreationSource::DELETION_RECOVERY,
                CustomerCreationSource::DIRECTORY,
                CustomerCreationSource::EGIFTING,
                CustomerCreationSource::EMAIL_COLLECTION,
                CustomerCreationSource::FEEDBACK,
                CustomerCreationSource::IMPORT,
                CustomerCreationSource::INVOICES,
                CustomerCreationSource::LOYALTY,
                CustomerCreationSource::MARKETING,
                CustomerCreationSource::MERGE,
                CustomerCreationSource::ONLINE_STORE,
                CustomerCreationSource::INSTANT_PROFILE,
                CustomerCreationSource::TERMINAL,
                CustomerCreationSource::THIRD_PARTY,
                CustomerCreationSource::THIRD_PARTY_IMPORT,
                CustomerCreationSource::UNMERGE_RECOVERY,
            ])->nullable();

            // Version control for optimistic locking
            $table->integer('payment_service_version')->nullable()->after('payment_service_id');

            // Complex objects stored as JSON
            $table->json('preferences')->nullable();
            $table->json('group_ids')->nullable();
            $table->json('segment_ids')->nullable();
            $table->json('tax_ids')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('nikolag_customers', function (Blueprint $table) {
            // Drop all added columns
            $table->dropColumn([
                // Profile fields
                'birthday',
                'reference_id',
                'creation_source',
                'payment_service_version',

                // Complex objects
                'preferences',
                'group_ids',
                'segment_ids',
                'tax_ids',
            ]);
        });
    }
};
