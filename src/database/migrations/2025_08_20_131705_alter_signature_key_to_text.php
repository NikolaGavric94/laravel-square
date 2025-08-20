<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Store existing unencrypted data
        $existingData = DB::table('nikolag_webhook_subscriptions')
            ->select(['id', 'signature_key'])
            ->get();

        // Step 2: Change column type to accommodate encrypted data
        Schema::table('nikolag_webhook_subscriptions', function (Blueprint $table) {
            $table->text('signature_key')->change();
        });

        // Step 3: Encrypt existing unencrypted signature keys
        foreach ($existingData as $record) {
            if ($record->signature_key) {
                // Only encrypt if it's not already encrypted
                // Laravel's encrypted cast produces base64 strings that start with specific patterns
                if (!$this->isAlreadyEncrypted($record->signature_key)) {
                    $encryptedKey = Crypt::encryptString($record->signature_key);

                    DB::table('nikolag_webhook_subscriptions')
                        ->where('id', $record->id)
                        ->update(['signature_key' => $encryptedKey]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Decrypt existing encrypted data before changing column type
        $existingData = DB::table('nikolag_webhook_subscriptions')
            ->select(['id', 'signature_key'])
            ->get();

        // Step 2: Attempt to decrypt encrypted signature keys
        foreach ($existingData as $record) {
            if ($record->signature_key && $this->isAlreadyEncrypted($record->signature_key)) {
                try {
                    $decryptedKey = Crypt::decryptString($record->signature_key);

                    // Ensure the decrypted key fits in a string column (255 chars max)
                    if (strlen($decryptedKey) <= 255) {
                        DB::table('nikolag_webhook_subscriptions')
                            ->where('id', $record->id)
                            ->update(['signature_key' => $decryptedKey]);
                    } else {
                        // Log warning - signature key too long for string column
                        Log::warning("Webhook signature key for ID {$record->id} too long for string column during rollback");
                    }
                } catch (Exception $e) {
                    // Log decryption failure but continue migration
                    Log::warning("Failed to decrypt signature key for webhook subscription ID {$record->id}: " . $e->getMessage());
                }
            }
        }

        // Step 3: Change column type back to string
        Schema::table('nikolag_webhook_subscriptions', function (Blueprint $table) {
            $table->string('signature_key')->change();
        });
    }

    /**
     * Check if a value appears to be already encrypted by Laravel's Crypt class.
     *
     * @param string $value
     * @return bool
     */
    private function isAlreadyEncrypted(string $value): bool
    {
        // Laravel's encrypted values are base64 encoded JSON strings
        // They typically start with "eyJ" when base64 decoded starts with "{"
        if (strlen($value) < 20) {
            return false; // Too short to be encrypted
        }

        // Try to decode as base64 and check if it's a JSON structure
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);
        return $json !== null && isset($json['iv']) && isset($json['value']) && isset($json['mac']);
    }
};
