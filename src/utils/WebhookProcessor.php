<?php

namespace Nikolag\Square\Utils;

use Carbon\Carbon;
use Exception;
use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Square\Utils\WebhooksHelper;

class WebhookProcessor
{
    /**
     * Verify and process a webhook payload.
     *
     * @param  array  $headers  The webhook headers
     * @param  string  $payload  The raw webhook payload
     * @param  WebhookSubscription  $subscription  The webhook subscription
     * @return WebhookEvent The created webhook event model
     *
     * @throws InvalidSquareSignatureException
     */
    public static function verifyAndProcess(array $headers, string $payload, WebhookSubscription $subscription): WebhookEvent
    {
        // Get the signature header
        $signature = $headers['x-square-hmacsha256-signature'][0] ?? $headers['X-Square-HmacSha256-Signature'][0] ?? null;

        if (! $signature) {
            throw new InvalidSquareSignatureException('Missing webhook signature header');
        }

        // Verify the signature
        if (! WebhooksHelper::isValidWebhookEventSignature($payload, $signature, $subscription->signature_key, $subscription->notification_url)) {
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

        if (! $eventId || ! $eventType || ! $eventTime) {
            throw new InvalidSquareSignatureException('Missing required event fields');
        }

        // Prepare webhook event data
        $webhookEventData = [
            'square_event_id' => $eventId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'event_time' => $eventTime,
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id,
        ];

        // Add retry data if present
        $retryData = self::extractRetryData($headers);
        if ($retryData) {
            $webhookEventData['retry_reason'] = $retryData['retry_reason'];
            $webhookEventData['retry_number'] = $retryData['retry_number'];
            $webhookEventData['initial_delivery_timestamp'] = $retryData['initial_delivery_timestamp'];
        }

        $existingEvent = WebhookEvent::where('square_event_id', $eventId)->first();
        if ($existingEvent) {
            // If the event already exists, update it instead of creating a new one
            $existingEvent->update($webhookEventData);

            return $existingEvent;
        }

        // Create and return the webhook event
        return WebhookEvent::create($webhookEventData);
    }

    /**
     * Extract retry data from webhook headers.
     *
     * @param  array  $headers  The webhook headers
     * @return array|null
     */
    private static function extractRetryData(array $headers): ?array
    {
        // Check for Square retry headers (can be in different formats)
        $retryReason = $headers['square-retry-reason'][0] ?? $headers['Square-Retry-Reason'][0] ?? null;
        $retryNumber = $headers['square-retry-number'][0] ?? $headers['Square-Retry-Number'][0] ?? null;
        $initialDeliveryTimestamp = $headers['square-initial-delivery-timestamp'][0] ?? $headers['Square-Initial-Delivery-Timestamp'][0] ?? null;

        // If no retry headers are present, this is not a retry
        if (! $retryReason || ! $retryNumber || ! $initialDeliveryTimestamp) {
            return null;
        }

        try {
            $parsedTimestamp = Carbon::parse($initialDeliveryTimestamp);
        } catch (Exception $e) {
            // If timestamp parsing fails, treat as non-retry
            return null;
        }

        return [
            'retry_reason' => $retryReason,
            'retry_number' => (int) $retryNumber,
            'initial_delivery_timestamp' => $parsedTimestamp,
        ];
    }

    /**
     * Test webhook signature verification with sample data.
     * Generates signatures compatible with Square's WebhooksHelper::isValidWebhookEventSignature method.
     *
     * @param  string  $signatureKey  The webhook signature key.
     * @param  string  $notificationUrl  The webhook notification URL.
     * @param  string  $requestBody  The raw JSON payload to sign.
     * @return string
     */
    public static function generateTestSignature(string $signatureKey, string $notificationUrl, ?string $requestBody = null): string
    {
        // Generate default test payload if none provided
        if ($requestBody === null) {
            $testPayload = [
                'merchant_id' => 'test-merchant',
                'type' => 'test.webhook',
                'event_id' => 'test-event-'.uniqid(),
                'created_at' => now()->toISOString(),
                'data' => [
                    'type' => 'test',
                    'id' => 'test-object-id',
                    'object' => [
                        'test' => true,
                    ],
                ],
            ];
            $requestBody = json_encode($testPayload);
        }

        // Use Square's exact algorithm for signature generation
        // Perform UTF-8 encoding to bytes
        $payload = $notificationUrl.$requestBody;
        $payloadBytes = mb_convert_encoding($payload, 'UTF-8');
        $signatureKeyBytes = mb_convert_encoding($signatureKey, 'UTF-8');

        // Compute the hash value (raw binary)
        $hash = hash_hmac('sha256', $payloadBytes, $signatureKeyBytes, true);

        // Base64 encode the hash (Square's format)
        return base64_encode($hash);
    }

    /**
     * Validate that the webhook payload contains required fields for order events.
     *
     * @param  array  $eventData  The parsed webhook event data
     * @return bool
     */
    public static function isValidOrderEvent(array $eventData): bool
    {
        $eventType = $eventData['type'] ?? '';

        if (! str_starts_with($eventType, 'order.')) {
            return false;
        }

        // Check for required order event structure
        $requiredFields = [
            'data.type',
            'data.id',
            'data.object.order.id',
        ];

        foreach ($requiredFields as $field) {
            if (! self::hasNestedKey($eventData, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a nested key exists in an array.
     *
     * @param  array  $array  The array to search
     * @param  string  $key  The dot-notation key to find
     * @return bool
     */
    private static function hasNestedKey(array $array, string $key): bool
    {
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (! is_array($array) || ! array_key_exists($k, $array)) {
                return false;
            }
            $array = $array[$k];
        }

        return true;
    }

    /**
     * Extract order ID from webhook event data.
     *
     * @param  array  $eventData  The parsed webhook event data
     * @return string|null
     */
    public static function extractOrderId(array $eventData): ?string
    {
        return $eventData['data']['object']['order']['id'] ?? null;
    }

    /**
     * Extract merchant ID from webhook event data.
     *
     * @param  array  $eventData  The parsed webhook event data
     * @return string|null
     */
    public static function extractMerchantId(array $eventData): ?string
    {
        return $eventData['merchant_id'] ?? null;
    }

    /**
     * Extract location ID from webhook event data.
     *
     * @param  array  $eventData  The parsed webhook event data
     * @return string|null
     */
    public static function extractLocationId(array $eventData): ?string
    {
        return $eventData['data']['object']['order']['location_id'] ?? null;
    }
}
