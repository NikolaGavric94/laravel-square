<?php

namespace Nikolag\Square\Utils;

use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Models\WebhookSubscription;

class WebhookVerifier
{
    /**
     * Verify the webhook signature from Square.
     *
     * @param string $payload The raw webhook payload
     * @param string $signature The signature header from Square
     * @param string $signatureKey The webhook signature key
     * @param string $notificationUrl The webhook notification URL
     * @return bool
     */
    public static function verify(
        string $payload,
        string $signature,
        string $signatureKey,
        string $notificationUrl
    ): bool {
        // Square uses HMAC-SHA256 for webhook signatures
        // The signature is computed as: hash_hmac('sha256', $notificationUrl . $payload, $signatureKey)
        $expectedSignature = hash_hmac('sha256', $notificationUrl . $payload, $signatureKey);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify and process a webhook payload.
     *
     * @param array $headers The webhook headers
     * @param string $payload The raw webhook payload
     * @param WebhookSubscription $subscription The webhook subscription
     *
     * @throws InvalidSquareSignatureException
     *
     * @return array The parsed webhook event data
     */
    public static function verifyAndProcess(
        array $headers,
        string $payload,
        WebhookSubscription $subscription
    ): array {
        // Get the signature header
        $signature = $headers['x-square-hmacsha256-signature'] ??
            $headers['X-Square-HmacSha256-Signature'] ??
            null;

        if (!$signature) {
            throw new InvalidSquareSignatureException('Missing webhook signature header');
        }

        // Verify the signature
        if (!self::verify($payload, $signature, $subscription->signature_key, $subscription->notification_url)) {
            throw new InvalidSquareSignatureException('Invalid webhook signature');
        }

        // Parse the payload
        $eventData = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidSquareSignatureException('Invalid JSON payload');
        }

        // Extract event information
        $eventId = $eventData['event_id'] ?? null;
        $eventType = $eventData['type'] ?? null;
        $eventTime = $eventData['created_at'] ?? null;

        if (!$eventId || !$eventType || !$eventTime) {
            throw new InvalidSquareSignatureException('Missing required event fields');
        }

        return $eventData;
    }

    /**
     * Test webhook signature verification with sample data.
     *
     * @param string $signatureKey The webhook signature key
     * @param string $notificationUrl The webhook notification URL
     * @return array Test data for signature verification
     */
    public static function generateTestSignature(
        string $signatureKey,
        string $notificationUrl
    ): array {
        $testPayload = json_encode([
            'merchant_id' => 'test-merchant',
            'type' => 'test.webhook',
            'event_id' => 'test-event-' . uniqid(),
            'created_at' => now()->toISOString(),
            'data' => [
                'type' => 'test',
                'id' => 'test-object-id',
                'object' => [
                    'test' => true
                ]
            ]
        ]);

        $signature = hash_hmac('sha256', $notificationUrl . $testPayload, $signatureKey);

        return [
            'payload' => $testPayload,
            'signature' => $signature,
            'headers' => [
                'X-Square-HmacSha256-Signature' => $signature,
                'Content-Type' => 'application/json',
            ]
        ];
    }

    /**
     * Validate that the webhook payload contains required fields for order events.
     *
     * @param array $eventData The parsed webhook event data
     * @return bool
     */
    public static function isValidOrderEvent(array $eventData): bool
    {
        $eventType = $eventData['type'] ?? '';

        if (!str_starts_with($eventType, 'order.')) {
            return false;
        }

        // Check for required order event structure
        $requiredFields = [
            'data.type',
            'data.id',
            'data.object.order.id',
        ];

        foreach ($requiredFields as $field) {
            if (!self::hasNestedKey($eventData, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a nested key exists in an array.
     *
     * @param array $array The array to search
     * @param string $key The dot-notation key to find
     * @return bool
     */
    private static function hasNestedKey(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        
        foreach ($keys as $k) {
            if (!is_array($array) || !array_key_exists($k, $array)) {
                return false;
            }
            $array = $array[$k];
        }
        
        return true;
    }

    /**
     * Extract order ID from webhook event data.
     *
     * @param array $eventData The parsed webhook event data
     * @return string|null
     */
    public static function extractOrderId(array $eventData): ?string
    {
        return $eventData['data']['object']['order']['id'] ?? null;
    }

    /**
     * Extract merchant ID from webhook event data.
     *
     * @param array $eventData The parsed webhook event data
     * @return string|null
     */
    public static function extractMerchantId(array $eventData): ?string
    {
        return $eventData['merchant_id'] ?? null;
    }

    /**
     * Extract location ID from webhook event data.
     *
     * @param array $eventData The parsed webhook event data
     * @return string|null
     */
    public static function extractLocationId(array $eventData): ?string
    {
        return $eventData['data']['object']['order']['location_id'] ?? null;
    }
}
