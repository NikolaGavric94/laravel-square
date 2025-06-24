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

    public function test_verify_and_process_handles_lowercase_signature_header()
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

    public function test_verify_and_process_throws_exception_for_missing_signature_header()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $headers = ['Content-Type' => 'application/json'];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing webhook signature header');

        WebhookVerifier::verifyAndProcess($headers, $this->validPayload, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_invalid_signature()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $headers = ['x-square-hmacsha256-signature' => 'invalid-signature'];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        WebhookVerifier::verifyAndProcess($headers, $this->validPayload, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_invalid_json()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $invalidPayload = 'invalid-json-payload';
        $signature = hash_hmac('sha256', $this->testNotificationUrl . $invalidPayload, $this->testSignatureKey);
        $headers = ['x-square-hmacsha256-signature' => $signature];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        WebhookVerifier::verifyAndProcess($headers, $invalidPayload, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_missing_event_id()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $payloadWithoutEventId = json_encode([
            'type' => 'order.created',
            'created_at' => '2024-01-01T12:00:00Z'
        ]);

        $signature = hash_hmac('sha256', $this->testNotificationUrl . $payloadWithoutEventId, $this->testSignatureKey);
        $headers = ['x-square-hmacsha256-signature' => $signature];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookVerifier::verifyAndProcess($headers, $payloadWithoutEventId, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_missing_event_type()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $payloadWithoutType = json_encode([
            'event_id' => 'event-123',
            'created_at' => '2024-01-01T12:00:00Z'
        ]);

        $signature = hash_hmac('sha256', $this->testNotificationUrl . $payloadWithoutType, $this->testSignatureKey);
        $headers = ['x-square-hmacsha256-signature' => $signature];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookVerifier::verifyAndProcess($headers, $payloadWithoutType, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_missing_created_at()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $payloadWithoutCreatedAt = json_encode([
            'event_id' => 'event-123',
            'type' => 'order.created'
        ]);

        $signature = hash_hmac('sha256', $this->testNotificationUrl . $payloadWithoutCreatedAt, $this->testSignatureKey);
        $headers = ['x-square-hmacsha256-signature' => $signature];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookVerifier::verifyAndProcess($headers, $payloadWithoutCreatedAt, $subscription);
    }

    public function test_generate_test_signature_creates_valid_data()
    {
        $testData = WebhookVerifier::generateTestSignature($this->testSignatureKey, $this->testNotificationUrl);

        $this->assertArrayHasKey('payload', $testData);
        $this->assertArrayHasKey('signature', $testData);
        $this->assertArrayHasKey('headers', $testData);

        $this->assertJson($testData['payload']);
        $this->assertNotEmpty($testData['signature']);
        $this->assertArrayHasKey('X-Square-HmacSha256-Signature', $testData['headers']);
        $this->assertEquals($testData['signature'], $testData['headers']['X-Square-HmacSha256-Signature']);
    }

    public function test_generate_test_signature_produces_verifiable_signature()
    {
        $testData = WebhookVerifier::generateTestSignature($this->testSignatureKey, $this->testNotificationUrl);

        $isValid = WebhookVerifier::verify(
            $testData['payload'],
            $testData['signature'],
            $this->testSignatureKey,
            $this->testNotificationUrl
        );

        $this->assertTrue($isValid);
    }

    public function test_generate_test_signature_includes_required_fields()
    {
        $testData = WebhookVerifier::generateTestSignature($this->testSignatureKey, $this->testNotificationUrl);
        $payload = json_decode($testData['payload'], true);

        $this->assertArrayHasKey('merchant_id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('event_id', $payload);
        $this->assertArrayHasKey('created_at', $payload);
        $this->assertArrayHasKey('data', $payload);

        $this->assertEquals('test.webhook', $payload['type']);
        $this->assertEquals('test-merchant', $payload['merchant_id']);
        $this->assertStringStartsWith('test-event-', $payload['event_id']);
    }

    public function test_is_valid_order_event_returns_true_for_valid_order_events()
    {
        $validOrderEvents = [
            'order.created' => $this->validEventData,
            'order.updated' => array_merge($this->validEventData, ['type' => 'order.updated']),
            'order.fulfilled' => array_merge($this->validEventData, ['type' => 'order.fulfilled']),
        ];

        foreach ($validOrderEvents as $eventType => $eventData) {
            $result = WebhookVerifier::isValidOrderEvent($eventData);
            $this->assertTrue($result, "Failed for event type: {$eventType}");
        }
    }

    public function test_is_valid_order_event_returns_false_for_non_order_events()
    {
        $nonOrderEvents = [
            'payment.created',
            'customer.created',
            'inventory.updated',
            'booking.created'
        ];

        foreach ($nonOrderEvents as $eventType) {
            $eventData = array_merge($this->validEventData, ['type' => $eventType]);
            $result = WebhookVerifier::isValidOrderEvent($eventData);
            $this->assertFalse($result, "Should fail for event type: {$eventType}");
        }
    }

    public function test_is_valid_order_event_returns_false_for_missing_required_fields()
    {
        $testCases = [
            'missing_data_type' => [
                'type' => 'order.created',
                'data' => [
                    'id' => 'test-id',
                    'object' => ['order' => ['id' => 'order-123']]
                ]
            ],
            'missing_data_id' => [
                'type' => 'order.created',
                'data' => [
                    'type' => 'order',
                    'object' => ['order' => ['id' => 'order-123']]
                ]
            ],
            'missing_order_id' => [
                'type' => 'order.created',
                'data' => [
                    'type' => 'order',
                    'id' => 'test-id',
                    'object' => ['order' => []]
                ]
            ],
            'missing_data_object' => [
                'type' => 'order.created',
                'data' => [
                    'type' => 'order',
                    'id' => 'test-id'
                ]
            ],
            'missing_order_object' => [
                'type' => 'order.created',
                'data' => [
                    'type' => 'order',
                    'id' => 'test-id',
                    'object' => []
                ]
            ]
        ];

        foreach ($testCases as $testName => $eventData) {
            $result = WebhookVerifier::isValidOrderEvent($eventData);
            $this->assertFalse($result, "Should fail for case: {$testName}");
        }
    }

    public function test_extract_order_id_returns_correct_id()
    {
        $orderId = WebhookVerifier::extractOrderId($this->validEventData);
        $this->assertEquals('order-456', $orderId);
    }

    public function test_extract_order_id_returns_null_for_missing_id()
    {
        $eventDataWithoutOrderId = [
            'data' => [
                'object' => [
                    'order' => []
                ]
            ]
        ];

        $orderId = WebhookVerifier::extractOrderId($eventDataWithoutOrderId);
        $this->assertNull($orderId);
    }

    public function test_extract_order_id_returns_null_for_malformed_data()
    {
        $malformedData = [
            'data' => [
                'object' => 'not-an-array'
            ]
        ];

        $orderId = WebhookVerifier::extractOrderId($malformedData);
        $this->assertNull($orderId);
    }

    public function test_extract_merchant_id_returns_correct_id()
    {
        $merchantId = WebhookVerifier::extractMerchantId($this->validEventData);
        $this->assertEquals('test-merchant-123', $merchantId);
    }

    public function test_extract_merchant_id_returns_null_for_missing_id()
    {
        $eventDataWithoutMerchantId = [
            'type' => 'order.created'
        ];

        $merchantId = WebhookVerifier::extractMerchantId($eventDataWithoutMerchantId);
        $this->assertNull($merchantId);
    }

    public function test_extract_location_id_returns_correct_id()
    {
        $locationId = WebhookVerifier::extractLocationId($this->validEventData);
        $this->assertEquals('location-789', $locationId);
    }

    public function test_extract_location_id_returns_null_for_missing_id()
    {
        $eventDataWithoutLocationId = [
            'data' => [
                'object' => [
                    'order' => []
                ]
            ]
        ];

        $locationId = WebhookVerifier::extractLocationId($eventDataWithoutLocationId);
        $this->assertNull($locationId);
    }

    public function test_extract_location_id_returns_null_for_malformed_data()
    {
        $malformedData = [
            'data' => 'not-an-array'
        ];

        $locationId = WebhookVerifier::extractLocationId($malformedData);
        $this->assertNull($locationId);
    }

    public function test_has_nested_key_functionality_through_is_valid_order_event()
    {
        $validOrderEvent = [
            'type' => 'order.created',
            'data' => [
                'type' => 'order',
                'id' => 'test-id',
                'object' => [
                    'order' => [
                        'id' => 'order-123'
                    ]
                ]
            ]
        ];

        $this->assertTrue(WebhookVerifier::isValidOrderEvent($validOrderEvent));
    }

    public function test_verify_and_process_with_empty_headers()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing webhook signature header');

        WebhookVerifier::verifyAndProcess([], $this->validPayload, $subscription);
    }

    public function test_verify_with_special_characters_in_payload()
    {
        $specialPayload = json_encode([
            'message' => 'Test with special chars: @#$%^&*()_+[]{}|;:,.<>?',
            'unicode' => 'Testing unicode: 你好世界',
            'data' => ['key' => 'value with spaces and symbols !@#$%']
        ]);

        $signature = hash_hmac('sha256', $this->testNotificationUrl . $specialPayload, $this->testSignatureKey);

        $result = WebhookVerifier::verify(
            $specialPayload,
            $signature,
            $this->testSignatureKey,
            $this->testNotificationUrl
        );

        $this->assertTrue($result);
    }
}
