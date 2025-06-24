<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Tests\TestCase;

class SquareServiceWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test creating a webhook builder instance.
     */
    public function test_webhook_builder_creation(): void
    {
        $builder = Square::webhookBuilder();

        $this->assertInstanceOf(WebhookBuilder::class, $builder);
    }

    /**
     * Test marking a webhook event as processed.
     */
    public function test_mark_webhook_event_processed(): void
    {
        // Create a webhook event
        $event = WebhookEvent::create([
            'square_event_id' => 'event_123',
            'event_type' => 'order.created',
            'event_time' => now(),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => 1
        ]);

        // Execute the test
        $result = Square::markWebhookEventProcessed('event_123');

        // Assertions
        $this->assertTrue($result);

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    /**
     * Test marking a webhook event as failed.
     */
    public function test_mark_webhook_event_failed(): void
    {
        // Create a webhook event
        $event = WebhookEvent::create([
            'square_event_id' => 'event_123',
            'event_type' => 'order.created',
            'event_time' => now(),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => 1
        ]);

        // Execute the test
        $result = Square::markWebhookEventFailed('event_123', 'Test error message');

        // Assertions
        $this->assertTrue($result);

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
        $this->assertEquals('Test error message', $event->error_message);
        $this->assertNotNull($event->processed_at);
    }
}
