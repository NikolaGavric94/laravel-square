<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\WebhookVerifier;

class WebhookBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the WebhookBuilder can be created via the Square facade.
     *
     * @return void
     */
    public function test_can_create_webhook_builder()
    {
        $builder = Square::webhookBuilder();

        $this->assertInstanceOf(WebhookBuilder::class, $builder);
    }

    /**
     * Test that the WebhookBuilder can set properties correctly.
     *
     * @return void
     */
    public function test_webhook_builder_can_set_properties()
    {
        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl('https://example.com/webhook')
            ->addEventType('order.created')
            ->addEventType('order.updated')
            ->apiVersion('2024-06-04')
            ->enabled();

        $this->assertEquals('Test Webhook', $builder->getName());
        $this->assertEquals('https://example.com/webhook', $builder->getNotificationUrl());
        $this->assertEquals(['order.created', 'order.updated'], $builder->getEventTypes());
        $this->assertEquals('2024-06-04', $builder->getApiVersion());
    }

    /**
     * Test that the WebhookBuilder validates required fields.
     *
     * @return void
     */
    public function test_webhook_builder_validates_required_fields()
    {
        $builder = Square::webhookBuilder();

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Webhook name is required');

        $builder->buildCreateRequest();
    }

    /**
     * Test that the WebhookBuilder validates HTTPS URL.
     *
     * @return void
     */
    public function test_webhook_builder_validates_https_url()
    {
        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl('http://example.com/webhook')
            ->addEventType('order.created');

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Notification URL must be a valid HTTPS URL');

        $builder->buildCreateRequest();
    }

    /**
     * Test that the WebhookVerifier can verify valid signature.
     *
     * @return void
     */
    public function test_webhook_verifier_can_verify_valid_signature()
    {
        $payload = json_encode(['test' => 'data']);
        $notificationUrl = 'https://example.com/webhook';
        $signatureKey = 'test-signature-key';

        $expectedSignature = hash_hmac('sha256', $notificationUrl . $payload, $signatureKey);

        $result = WebhookVerifier::verify($payload, $expectedSignature, $signatureKey, $notificationUrl);

        $this->assertTrue($result);
    }

    /**
     * Test that the WebhookVerifier rejects invalid signature.
     *
     * @return void
     */
    public function test_webhook_verifier_rejects_invalid_signature()
    {
        $payload = json_encode(['test' => 'data']);
        $notificationUrl = 'https://example.com/webhook';
        $signatureKey = 'test-signature-key';
        $invalidSignature = 'invalid-signature';

        $result = WebhookVerifier::verify($payload, $invalidSignature, $signatureKey, $notificationUrl);

        $this->assertFalse($result);
    }

    /**
     * Test that the WebhookVerifier can generate test signature.
     *
     * @return void
     */
    public function test_webhook_verifier_can_generate_test_signature()
    {
        $signatureKey = 'test-signature-key';
        $notificationUrl = 'https://example.com/webhook';

        $testData = WebhookVerifier::generateTestSignature($signatureKey, $notificationUrl);

        $this->assertArrayHasKey('payload', $testData);
        $this->assertArrayHasKey('signature', $testData);
        $this->assertArrayHasKey('headers', $testData);

        // Verify the generated signature is valid
        $isValid = WebhookVerifier::verify(
            $testData['payload'],
            $testData['signature'],
            $signatureKey,
            $notificationUrl
        );

        $this->assertTrue($isValid);
    }

    /**
     * Test that the WebhookVerifier validates order event structure.
     *
     * @return void
     */
    public function test_webhook_verifier_validates_order_event_structure()
    {
        $validOrderEvent = [
            'type' => 'order.created',
            'data' => [
                'type' => 'order',
                'id' => 'test-id',
                'object' => [
                    'order' => [
                        'id' => 'order-123',
                        'location_id' => 'location-456'
                    ]
                ]
            ]
        ];

        $this->assertTrue(WebhookVerifier::isValidOrderEvent($validOrderEvent));

        $invalidOrderEvent = [
            'type' => 'order.created',
            'data' => [
                'type' => 'order',
                // Missing required fields
            ]
        ];

        $this->assertFalse(WebhookVerifier::isValidOrderEvent($invalidOrderEvent));
    }

    /**
     * Test that the WebhookVerifier can extract order data.
     *
     * @return void
     */
    public function test_webhook_verifier_can_extract_order_data()
    {
        $eventData = [
            'merchant_id' => 'merchant-123',
            'type' => 'order.created',
            'data' => [
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789'
                    ]
                ]
            ]
        ];

        $this->assertEquals('order-456', WebhookVerifier::extractOrderId($eventData));
        $this->assertEquals('merchant-123', WebhookVerifier::extractMerchantId($eventData));
        $this->assertEquals('location-789', WebhookVerifier::extractLocationId($eventData));
    }
}
