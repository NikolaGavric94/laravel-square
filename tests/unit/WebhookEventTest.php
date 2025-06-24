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
        $eventData = $event->event_data;

        $this->assertDatabaseHas('nikolag_webhook_events', [
            'square_event_id' => 'event-123',
            'event_type' => 'order.created',
            'status' => WebhookEvent::STATUS_PENDING,
            'subscription_id' => $subscription->id,
        ]);

        $this->assertEquals($eventData, $event->event_data);
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
    }
}
