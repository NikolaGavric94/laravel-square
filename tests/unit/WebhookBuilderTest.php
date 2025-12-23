<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\WebhookProcessor;

class WebhookBuilderTest extends TestCase
{
    use RefreshDatabase;

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
     * Test that the WebhookProcessor validates order event structure.
     *
     * @return void
     */
    public function test_webhook_processor_validates_order_event_structure()
    {
        $validOrderEvent = [
            'type' => 'order.created',
            'data' => [
                'type' => 'order',
                'id' => 'test-id',
                'object' => [
                    'order' => [
                        'id' => 'order-123',
                        'location_id' => 'location-456',
                    ],
                ],
            ],
        ];

        $this->assertTrue(WebhookProcessor::isValidOrderEvent($validOrderEvent));

        $invalidOrderEvent = [
            'type' => 'order.created',
            'data' => [
                'type' => 'order',
                // Missing required fields
            ],
        ];

        $this->assertFalse(WebhookProcessor::isValidOrderEvent($invalidOrderEvent));
    }

    /**
     * Test that the WebhookProcessor can extract order data.
     *
     * @return void
     */
    public function test_webhook_processor_can_extract_order_data()
    {
        $eventData = [
            'merchant_id' => 'merchant-123',
            'type' => 'order.created',
            'data' => [
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789',
                    ],
                ],
            ],
        ];

        $this->assertEquals('order-456', WebhookProcessor::extractOrderId($eventData));
        $this->assertEquals('merchant-123', WebhookProcessor::extractMerchantId($eventData));
        $this->assertEquals('location-789', WebhookProcessor::extractLocationId($eventData));
    }
}
