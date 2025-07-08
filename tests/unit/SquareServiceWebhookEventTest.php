<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;

class SquareServiceWebhookEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a test webhook subscription for testing purposes.
     *
     * @return WebhookSubscription
     */
    protected function createTestWebhookSubscription(): WebhookSubscription
    {
        return WebhookSubscription::create([
            'square_id' => 'test_webhook_subscription',
            'name' => 'Test Webhook Subscription',
            'notification_url' => 'https://example.com/webhook',
            'event_types' => ['order.created', 'order.updated', 'payment.created'],
            'api_version' => '2023-10-18',
            'signature_key' => 'test_signature_key',
            'is_enabled' => true,
            'is_active' => true,
        ]);
    }

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
        // Create required webhook subscription
        $subscription = $this->createTestWebhookSubscription();
        
        // Create a webhook event
        $event = WebhookEvent::create([
            'square_event_id' => 'event_123',
            'event_type' => 'order.created',
            'event_time' => now(),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id
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
        // Create required webhook subscription
        $subscription = $this->createTestWebhookSubscription();
        
        // Create a webhook event
        $event = WebhookEvent::create([
            'square_event_id' => 'event_123',
            'event_type' => 'order.created',
            'event_time' => now(),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id
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

    /**
     * Test that non-existent webhook event methods return false.
     */
    public function test_webhook_event_methods_with_non_existent_events(): void
    {
        // Test marking non-existent event as processed
        $result = Square::markWebhookEventProcessed('non_existent_event');
        $this->assertFalse($result);

        // Test marking non-existent event as failed
        $result = Square::markWebhookEventFailed('non_existent_event', 'Error message');
        $this->assertFalse($result);
    }

    /**
     * Test cleaning up old webhook events.
     */
    public function test_cleanup_old_webhook_events(): void
    {
        // Create required webhook subscription
        $subscription = $this->createTestWebhookSubscription();
        
        // Create test webhook events with different ages
        $oldEvent1 = new WebhookEvent([
            'square_event_id' => 'old_event_1',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(45),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PROCESSED,
            'webhook_subscription_id' => $subscription->id,
        ]);
        $oldEvent1->created_at = now()->subDays(45);
        $oldEvent1->save();

        $oldEvent2 = new WebhookEvent([
            'square_event_id' => 'old_event_2',
            'event_type' => 'order.updated',
            'event_time' => now()->subDays(35),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_FAILED,
            'webhook_subscription_id' => $subscription->id,
        ]);
        $oldEvent2->created_at = now()->subDays(35);
        $oldEvent2->save();

        $oldPendingEvent = new WebhookEvent([
            'square_event_id' => 'old_pending_event',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(40),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id,
        ]);
        $oldPendingEvent->created_at = now()->subDays(40);
        $oldPendingEvent->save();

        $recentEvent = new WebhookEvent([
            'square_event_id' => 'recent_event',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(10),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PROCESSED,
            'webhook_subscription_id' => $subscription->id,
        ]);
        $recentEvent->created_at = now()->subDays(10);
        $recentEvent->save();

        // Execute the test - cleanup events older than 30 days
        $deletedCount = Square::cleanupOldWebhookEvents(30);

        // Assertions - should delete old processed/failed events but not pending ones
        $this->assertEquals(2, $deletedCount);

        // Verify remaining events
        $remainingEvents = WebhookEvent::all();
        $this->assertCount(2, $remainingEvents);

        $remainingEventIds = $remainingEvents->pluck('square_event_id')->toArray();
        $this->assertContains('old_pending_event', $remainingEventIds);
        $this->assertContains('recent_event', $remainingEventIds);
    }
}
