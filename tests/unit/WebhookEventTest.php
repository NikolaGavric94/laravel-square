<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class WebhookEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Webhook event creation.
     *
     * @return void
     */
    public function test_webhook_event_make()
    {
        $event = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->make();

        $this->assertNotNull($event, 'Webhook event is null.');
    }

    /**
     * Webhook event persistence.
     *
     * @return void
     */
    public function test_webhook_event_create()
    {
        $subscription = factory(WebhookSubscription::class)->create();

        $event = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertDatabaseHas('nikolag_webhook_events', [
            'square_event_id' => $event->square_event_id,
            'event_type' => 'order.created',
            'status' => WebhookEvent::STATUS_PENDING,
            'subscription_id' => $subscription->id,
        ]);

        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
    }

    /**
     * Test fillable attributes.
     *
     * @return void
     */
    public function test_webhook_event_fillable_attributes()
    {
        $subscription = factory(WebhookSubscription::class)->create();

        $data = [
            'square_event_id' => 'event-123',
            'event_type' => 'order.created',
            'event_data' => ['test' => 'data'],
            'event_time' => now(),
            'status' => WebhookEvent::STATUS_PENDING,
            'processed_at' => now(),
            'error_message' => 'Test error',
            'subscription_id' => $subscription->id,
        ];

        $event = WebhookEvent::create($data);

        foreach ($data as $key => $value) {
            if (in_array($key, ['event_time', 'processed_at'])) {
                $this->assertInstanceOf(Carbon::class, $event->$key);
            } else {
                $this->assertEquals($value, $event->$key);
            }
        }
    }

    /**
     * Test casts.
     *
     * @return void
     */
    public function test_webhook_event_casts_work_correctly()
    {
        $event = factory(WebhookEvent::class)->create([
            'event_data' => ['test' => 'data', 'nested' => ['key' => 'value']],
            'event_time' => '2024-06-24 10:00:00',
            'processed_at' => '2024-06-24 11:00:00',
        ]);

        $this->assertIsArray($event->event_data);
        $this->assertInstanceOf(Carbon::class, $event->event_time);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
    }

    /**
     * Test webhook event belongs to subscription relationship.
     *
     * @return void
     */
    public function test_webhook_event_belongs_to_subscription_relationship()
    {
        $subscription = factory(WebhookSubscription::class)->create();
        $event = factory(WebhookEvent::class)->create([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertInstanceOf(WebhookSubscription::class, $event->subscription);
        $this->assertEquals($subscription->id, $event->subscription->id);
        $this->assertEquals($subscription->name, $event->subscription->name);
    }

    /**
     * Test webhook event status constants.
     *
     * @return void
     */
    public function test_webhook_event_status_constants()
    {
        $this->assertEquals('pending', WebhookEvent::STATUS_PENDING);
        $this->assertEquals('processed', WebhookEvent::STATUS_PROCESSED);
        $this->assertEquals('failed', WebhookEvent::STATUS_FAILED);
    }

    /**
     * Test webhook event scopes.
     *
     * @return void
     */
    public function test_webhook_event_pending_scope()
    {
        factory(WebhookEvent::class, 3)->create(['status' => WebhookEvent::STATUS_PENDING]);
        factory(WebhookEvent::class, 2)->create(['status' => WebhookEvent::STATUS_PROCESSED]);
        factory(WebhookEvent::class, 1)->create(['status' => WebhookEvent::STATUS_FAILED]);

        $pendingEvents = WebhookEvent::pending()->get();

        $this->assertCount(3, $pendingEvents);
        foreach ($pendingEvents as $event) {
            $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        }
    }

    /**
     * Test processed scope.
     *
     * @return void
     */
    public function test_webhook_event_processed_scope()
    {
        factory(WebhookEvent::class, 2)->create(['status' => WebhookEvent::STATUS_PENDING]);
        factory(WebhookEvent::class, 4)->create(['status' => WebhookEvent::STATUS_PROCESSED]);
        factory(WebhookEvent::class, 1)->create(['status' => WebhookEvent::STATUS_FAILED]);

        $processedEvents = WebhookEvent::processed()->get();

        $this->assertCount(4, $processedEvents);
        foreach ($processedEvents as $event) {
            $this->assertEquals(WebhookEvent::STATUS_PROCESSED, $event->status);
        }
    }

    /**
     * Test failed scope.
     *
     * @return void
     */
    public function test_webhook_event_failed_scope()
    {
        factory(WebhookEvent::class, 2)->create(['status' => WebhookEvent::STATUS_PENDING]);
        factory(WebhookEvent::class, 1)->create(['status' => WebhookEvent::STATUS_PROCESSED]);
        factory(WebhookEvent::class, 3)->create(['status' => WebhookEvent::STATUS_FAILED]);

        $failedEvents = WebhookEvent::failed()->get();

        $this->assertCount(3, $failedEvents);
        foreach ($failedEvents as $event) {
            $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
        }
    }
}
