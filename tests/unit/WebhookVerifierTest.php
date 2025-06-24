<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\WebhookVerifier;
use Nikolag\Square\Models\WebhookSubscription;

class WebhookVerifierTest extends TestCase
{
    private string $testSignatureKey = 'test-signature-key-123';
    private string $testNotificationUrl = 'https://example.com/webhook';
    private string $validPayload;
    private array $validEventData;

    public function setUp(): void
    {
        parent::setUp();

        $this->validPayload = json_encode([
            'merchant_id' => 'test-merchant-123',
            'type' => 'order.created',
            'event_id' => 'event-123',
            'created_at' => '2024-01-01T12:00:00Z',
            'data' => [
                'type' => 'order',
                'id' => 'order-data-123',
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789'
                    ]
                ]
            ]
        ]);

        $this->validEventData = [
            'merchant_id' => 'test-merchant-123',
            'type' => 'order.created',
            'event_id' => 'event-123',
            'created_at' => '2024-01-01T12:00:00Z',
            'data' => [
                'type' => 'order',
                'id' => 'order-data-123',
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789'
                    ]
                ]
            ]
        ];
    }

    public function test_verify_returns_true_for_valid_signature()
    {
        $signature = hash_hmac('sha256', $this->testNotificationUrl . $this->validPayload, $this->testSignatureKey);

        $result = WebhookVerifier::verify(
            $this->validPayload,
            $signature,
            $this->testSignatureKey,
            $this->testNotificationUrl
        );

        $this->assertTrue($result);
    }

    public function test_verify_returns_false_for_invalid_signature()
    {
        $invalidSignature = 'invalid-signature-hash';

        $result = WebhookVerifier::verify(
            $this->validPayload,
            $invalidSignature,
            $this->testSignatureKey,
            $this->testNotificationUrl
        );

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_tampered_payload()
    {
        $signature = hash_hmac('sha256', $this->testNotificationUrl . $this->validPayload, $this->testSignatureKey);
        $tamperedPayload = str_replace('order-456', 'order-999', $this->validPayload);

        $result = WebhookVerifier::verify(
            $tamperedPayload,
            $signature,
            $this->testSignatureKey,
            $this->testNotificationUrl
        );

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_wrong_notification_url()
    {
        $signature = hash_hmac('sha256', $this->testNotificationUrl . $this->validPayload, $this->testSignatureKey);
        $wrongUrl = 'https://malicious.com/webhook';

        $result = WebhookVerifier::verify(
            $this->validPayload,
            $signature,
            $this->testSignatureKey,
            $wrongUrl
        );

        $this->assertFalse($result);
    }

    public function test_verify_handles_empty_strings()
    {
        $result = WebhookVerifier::verify('', '', '', '');
        $this->assertFalse($result);
    }

    public function test_verify_and_process_succeeds_with_valid_data()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $signature = hash_hmac('sha256', $this->testNotificationUrl . $this->validPayload, $this->testSignatureKey);
        $headers = ['x-square-hmacsha256-signature' => $signature];

        $result = WebhookVerifier::verifyAndProcess($headers, $this->validPayload, $subscription);

        $this->assertEquals($this->validEventData, $result);
    }
}
