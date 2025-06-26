<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\WebhookProcessor;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Models\WebhookEvent;
use Square\Utils\WebhooksHelper;

class WebhookProcessorTest extends TestCase
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

    public function test_verify_and_process_succeeds_with_valid_data()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $this->validPayload
        );

        $headers = ['X-Square-HmacSha256-Signature' => [
                $signature
            ]
        ];

        $result = WebhookProcessor::verifyAndProcess($headers, $this->validPayload, $subscription);

        $this->assertInstanceOf(WebhookEvent::class, $result);
        $this->assertEquals('event-123', $result->square_event_id);
        $this->assertEquals('order.created', $result->event_type);
        $this->assertEquals($this->validEventData, $result->event_data);
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $result->status);
        $this->assertEquals($subscription->id, $result->webhook_subscription_id);
    }

    public function test_verify_and_process_handles_lowercase_signature_header()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $this->validPayload
        );

        $headers = ['x-square-hmacsha256-signature' => [
                $signature
            ]
        ];

        $result = WebhookProcessor::verifyAndProcess($headers, $this->validPayload, $subscription);

        $this->assertInstanceOf(WebhookEvent::class, $result);
        $this->assertEquals($this->validEventData, $result->event_data);
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

        WebhookProcessor::verifyAndProcess($headers, $this->validPayload, $subscription);
    }

    public function test_verify_and_process_throws_exception_for_invalid_signature()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $headers = [
            'x-square-hmacsha256-signature' => [
                'invalid-signature'
            ]
        ];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        WebhookProcessor::verifyAndProcess($headers, $this->validPayload, $subscription);
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

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $payloadWithoutEventId
        );

        $headers = ['x-square-hmacsha256-signature' => [
                $signature
            ]
        ];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookProcessor::verifyAndProcess($headers, $payloadWithoutEventId, $subscription);
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

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $payloadWithoutType
        );

        $headers = ['x-square-hmacsha256-signature' => [
                $signature
            ]
        ];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookProcessor::verifyAndProcess($headers, $payloadWithoutType, $subscription);
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

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $payloadWithoutCreatedAt
        );

        $headers = ['x-square-hmacsha256-signature' => [
                $signature
            ]
        ];

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        WebhookProcessor::verifyAndProcess($headers, $payloadWithoutCreatedAt, $subscription);
    }

    public function test_generate_test_signature_produces_verifiable_signature()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $signature = WebhookProcessor::generateTestSignature(
            $this->testSignatureKey,
            $this->testNotificationUrl,
            $this->validPayload
        );

        $isValid = WebhooksHelper::isValidWebhookEventSignature(
            $this->validPayload,
            $signature,
            $subscription->signature_key,
            $subscription->notification_url
        );

        $this->assertTrue($isValid);
    }

    public function test_generate_test_signature()
    {
        $signature = WebhookProcessor::generateTestSignature($this->testSignatureKey, $this->testNotificationUrl);
        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);
    }

    public function test_is_valid_order_event_returns_true_for_valid_order_events()
    {
        $validOrderEvents = [
            'order.created' => $this->validEventData,
            'order.updated' => array_merge($this->validEventData, ['type' => 'order.updated']),
            'order.fulfilled' => array_merge($this->validEventData, ['type' => 'order.fulfilled']),
        ];

        foreach ($validOrderEvents as $eventType => $eventData) {
            $result = WebhookProcessor::isValidOrderEvent($eventData);
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
            $result = WebhookProcessor::isValidOrderEvent($eventData);
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
            $result = WebhookProcessor::isValidOrderEvent($eventData);
            $this->assertFalse($result, "Should fail for case: {$testName}");
        }
    }

    public function test_extract_order_id_returns_correct_id()
    {
        $orderId = WebhookProcessor::extractOrderId($this->validEventData);
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

        $orderId = WebhookProcessor::extractOrderId($eventDataWithoutOrderId);
        $this->assertNull($orderId);
    }

    public function test_extract_order_id_returns_null_for_malformed_data()
    {
        $malformedData = [
            'data' => [
                'object' => 'not-an-array'
            ]
        ];

        $orderId = WebhookProcessor::extractOrderId($malformedData);
        $this->assertNull($orderId);
    }

    public function test_extract_merchant_id_returns_correct_id()
    {
        $merchantId = WebhookProcessor::extractMerchantId($this->validEventData);
        $this->assertEquals('test-merchant-123', $merchantId);
    }

    public function test_extract_merchant_id_returns_null_for_missing_id()
    {
        $eventDataWithoutMerchantId = [
            'type' => 'order.created'
        ];

        $merchantId = WebhookProcessor::extractMerchantId($eventDataWithoutMerchantId);
        $this->assertNull($merchantId);
    }

    public function test_extract_location_id_returns_correct_id()
    {
        $locationId = WebhookProcessor::extractLocationId($this->validEventData);
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

        $locationId = WebhookProcessor::extractLocationId($eventDataWithoutLocationId);
        $this->assertNull($locationId);
    }

    public function test_extract_location_id_returns_null_for_malformed_data()
    {
        $malformedData = [
            'data' => 'not-an-array'
        ];

        $locationId = WebhookProcessor::extractLocationId($malformedData);
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

        $this->assertTrue(WebhookProcessor::isValidOrderEvent($validOrderEvent));
    }

    public function test_verify_and_process_with_empty_headers()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'signature_key' => $this->testSignatureKey,
            'notification_url' => $this->testNotificationUrl,
        ]);

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing webhook signature header');

        WebhookProcessor::verifyAndProcess([], $this->validPayload, $subscription);
    }
}