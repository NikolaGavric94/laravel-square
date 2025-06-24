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

    /**
     * Test forEventType scope.
     *
     * @return void
     */
    public function test_webhook_event_for_event_type_scope()
    {
        factory(WebhookEvent::class)->create(['event_type' => 'order.created']);
        factory(WebhookEvent::class)->create(['event_type' => 'order.updated']);
        factory(WebhookEvent::class, 2)->create(['event_type' => 'order.created']);
        factory(WebhookEvent::class)->create(['event_type' => 'payment.created']);

        $orderCreatedEvents = WebhookEvent::forEventType('order.created')->get();
        $orderUpdatedEvents = WebhookEvent::forEventType('order.updated')->get();
        $paymentCreatedEvents = WebhookEvent::forEventType('payment.created')->get();

        $this->assertCount(3, $orderCreatedEvents);
        $this->assertCount(1, $orderUpdatedEvents);
        $this->assertCount(1, $paymentCreatedEvents);
    }

    /**
     * Test isOrderEvent method.
     *
     * @return void
     */
    public function test_webhook_event_is_order_event_method()
    {
        $orderCreatedEvent = factory(WebhookEvent::class)->create(['event_type' => 'order.created']);
        $orderUpdatedEvent = factory(WebhookEvent::class)->create(['event_type' => 'order.updated']);
        $orderFulfillmentEvent = factory(WebhookEvent::class)->create(['event_type' => 'order.fulfillment.updated']);
        $paymentEvent = factory(WebhookEvent::class)->create(['event_type' => 'payment.created']);
        $customerEvent = factory(WebhookEvent::class)->create(['event_type' => 'customer.created']);

        $this->assertTrue($orderCreatedEvent->isOrderEvent());
        $this->assertTrue($orderUpdatedEvent->isOrderEvent());
        $this->assertTrue($orderFulfillmentEvent->isOrderEvent());
        $this->assertFalse($paymentEvent->isOrderEvent());
        $this->assertFalse($customerEvent->isOrderEvent());
    }

    /**
     * Test isPaymentEvent method.
     *
     * @return void
     */
    public function test_webhook_event_is_payment_event_method()
    {
        $paymentCreatedEvent = factory(WebhookEvent::class)->create(['event_type' => 'payment.created']);
        $paymentUpdatedEvent = factory(WebhookEvent::class)->create(['event_type' => 'payment.updated']);
        $orderEvent = factory(WebhookEvent::class)->create(['event_type' => 'order.created']);
        $customerEvent = factory(WebhookEvent::class)->create(['event_type' => 'customer.created']);

        $this->assertTrue($paymentCreatedEvent->isPaymentEvent());
        $this->assertTrue($paymentUpdatedEvent->isPaymentEvent());
        $this->assertFalse($orderEvent->isPaymentEvent());
        $this->assertFalse($customerEvent->isPaymentEvent());
    }

    /**
     * Test getOrderId method.
     *
     * @return void
     */
    public function test_webhook_event_get_order_id_method()
    {
        $event = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create();

        $this->assertEquals('order-456', $event->getOrderId());
    }

    /**
     * Test getOrderId method returns null for missing data.
     *
     * @return void
     */
    public function test_webhook_event_get_order_id_returns_null_for_missing_data()
    {
        $event = factory(WebhookEvent::class)->create([
            'event_data' => ['some' => 'other_data']
        ]);

        $this->assertNull($event->getOrderId());
    }

    /**
     * Test getPaymentId method.
     *
     * @return void
     */
    public function test_webhook_event_get_payment_id_method()
    {
        $event = factory(WebhookEvent::class)->states('PAYMENT_CREATED_EVENT')->create();

        // Modify the event_data to have a specific payment ID
        $event->event_data['data']['object']['payment']['id'] = 'payment-123';
        $event->save();

        $this->assertEquals('payment-123', $event->getPaymentId());
    }

    /**
     * Test getMerchantId method.
     *
     * @return void
     */
    public function test_webhook_event_get_merchant_id_method()
    {
        $eventData = [
            'merchant_id' => 'merchant-123',
            'type' => 'order.created'
        ];

        $event = factory(WebhookEvent::class)->create([
            'event_data' => $eventData
        ]);

        $this->assertEquals('merchant-123', $event->getMerchantId());
    }

    /**
     * Test getLocationId method for order events.
     *
     * @return void
     */
    public function test_webhook_event_get_location_id_method_for_order_events()
    {
        $event = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create();

        $this->assertEquals('location-789', $event->getLocationId());
    }

    /**
     * Test getLocationId method for payment events.
     *
     * @return void
     */
    public function test_webhook_event_get_location_id_method_for_payment_events()
    {
        $event = factory(WebhookEvent::class)->states('PAYMENT_CREATED_EVENT')->create();

        $this->assertEquals('location-242', $event->getLocationId());
    }

    /**
     * Test markAsProcessed method.
     *
     * @return void
     */
    public function test_webhook_event_mark_as_processed_method()
    {
        $event = factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PENDING,
            'processed_at' => null,
            'error_message' => 'Previous error',
        ]);

        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertNull($event->processed_at);
        $this->assertEquals('Previous error', $event->error_message);

        $result = $event->markAsProcessed();

        $this->assertTrue($result);
        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->error_message);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
    }

    /**
     * Test markAsFailed method.
     *
     * @return void
     */
    public function test_webhook_event_mark_as_failed_method()
    {
        $event = factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PENDING,
            'processed_at' => null,
            'error_message' => null,
        ]);

        $errorMessage = 'Processing failed due to invalid data';
        $result = $event->markAsFailed($errorMessage);

        $this->assertTrue($result);
        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertEquals($errorMessage, $event->error_message);
        $this->assertInstanceOf(Carbon::class, $event->processed_at);
    }

    /**
     * Test status checking methods.
     *
     * @return void
     */
    public function test_webhook_event_status_checking_methods()
    {
        $pendingEvent = factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PENDING
        ]);
        $processedEvent = factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PROCESSED
        ]);
        $failedEvent = factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_FAILED
        ]);

        // Test pending event
        $this->assertTrue($pendingEvent->isPending());
        $this->assertFalse($pendingEvent->isProcessed());
        $this->assertFalse($pendingEvent->isFailed());

        // Test processed event
        $this->assertFalse($processedEvent->isPending());
        $this->assertTrue($processedEvent->isProcessed());
        $this->assertFalse($processedEvent->isFailed());

        // Test failed event
        $this->assertFalse($failedEvent->isPending());
        $this->assertFalse($failedEvent->isProcessed());
        $this->assertTrue($failedEvent->isFailed());
    }

}
