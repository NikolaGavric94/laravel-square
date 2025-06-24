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
        $newEventData = $event->event_data;
        $newEventData['data']['object']['payment']['id'] = 'payment-123';
        $event->event_data = $newEventData;
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

    /**
     * Test getEventObject method.
     *
     * @return void
     */
    public function test_webhook_event_get_event_object_method()
    {
        $event = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create();

        $eventObject = $event->getEventObject();

        $this->assertIsArray($eventObject);
        $this->assertArrayHasKey($event->getObjectTypeKey(), $eventObject);
        $this->assertArrayHasKey('order_created', $eventObject);
    }

    /**
     * Test getDescription method.
     *
     * @return void
     */
    public function test_webhook_event_get_description_method()
    {
        $orderEvent = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create();

        $paymentEvent = factory(WebhookEvent::class)->states('PAYMENT_CREATED_EVENT')->create();

        $otherEvent = factory(WebhookEvent::class)->create([
            'event_type' => 'customer.created'
        ]);

        $this->assertEquals('Order event (order.created) for order order-456', $orderEvent->getDescription());
        $this->assertEquals('Payment event (payment.created) for payment payment_id_444', $paymentEvent->getDescription());
        $this->assertEquals('Webhook event (customer.created)', $otherEvent->getDescription());
    }

    /**
     * Test chaining multiple scopes.
     *
     * @return void
     */
    public function test_webhook_event_multiple_scopes_can_be_chained()
    {
        factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PENDING,
            'event_type' => 'order.created',
        ]);

        factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PROCESSED,
            'event_type' => 'order.created',
        ]);

        factory(WebhookEvent::class)->create([
            'status' => WebhookEvent::STATUS_PENDING,
            'event_type' => 'payment.created',
        ]);

        $pendingOrderEvents = WebhookEvent::pending()
            ->forEventType('order.created')
            ->get();

        $this->assertCount(1, $pendingOrderEvents);
        $first = $pendingOrderEvents->first();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $first->status);
        $this->assertEquals('order.created', $first->event_type);
    }

    /**
     * Test factory generates realistic data.
     *
     * @return void
     */
    public function test_webhook_event_factory_generates_realistic_data()
    {
        $event = factory(WebhookEvent::class)->create();

        // Test square_event_id format
        $this->assertStringStartsWith('event_', $event->square_event_id);

        // Test event_type is valid
        $validEventTypes = ['order.created', 'order.updated', 'order.fulfillment.updated', 'payment.created', 'payment.updated'];
        $this->assertContains($event->event_type, $validEventTypes);

        // Test event_data is valid array
        $this->assertIsArray($event->event_data);
        $this->assertNotEmpty($event->event_data);

        // Test status is valid
        $validStatuses = [WebhookEvent::STATUS_PENDING, WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_FAILED];
        $this->assertContains($event->status, $validStatuses);

        // Test subscription relationship exists
        $this->assertNotNull($event->subscription_id);
        $this->assertInstanceOf(WebhookSubscription::class, $event->subscription);
    }

    /**
     * Test batch operations on webhook events.
     *
     * @return void
     */
    public function test_webhook_event_batch_operations()
    {
        // Create multiple events
        $events = factory(WebhookEvent::class, 5)->create([
            'status' => WebhookEvent::STATUS_PENDING
        ]);

        $this->assertCount(5, $events);
        $this->assertCount(5, WebhookEvent::all());

        // Test batch updates
        WebhookEvent::whereIn('id', $events->pluck('id'))
            ->update(['status' => WebhookEvent::STATUS_PROCESSED]);

        $processedCount = WebhookEvent::where('status', WebhookEvent::STATUS_PROCESSED)->count();
        $this->assertEquals(5, $processedCount);
    }

    /**
     * Test factory states work correctly.
     *
     * @return void
     */
    public function test_webhook_event_factory_states()
    {
        $pendingEvent = factory(WebhookEvent::class)->states('PENDING')->create();
        $processedEvent = factory(WebhookEvent::class)->states('PROCESSED')->create();
        $failedEvent = factory(WebhookEvent::class)->states('FAILED')->create();
        $orderEvent = factory(WebhookEvent::class)->states('ORDER_CREATED_EVENT')->create();
        $paymentEvent = factory(WebhookEvent::class)->states('PAYMENT_CREATED_EVENT')->create();

        $this->assertEquals(WebhookEvent::STATUS_PENDING, $pendingEvent->status);
        $this->assertNull($pendingEvent->processed_at);
        $this->assertNull($pendingEvent->error_message);

        $this->assertEquals(WebhookEvent::STATUS_PROCESSED, $processedEvent->status);
        $this->assertNotNull($processedEvent->processed_at);
        $this->assertNull($processedEvent->error_message);

        $this->assertEquals(WebhookEvent::STATUS_FAILED, $failedEvent->status);
        $this->assertNotNull($failedEvent->processed_at);
        $this->assertNotNull($failedEvent->error_message);

        $this->assertTrue($orderEvent->isOrderEvent());
        $this->assertTrue($paymentEvent->isPaymentEvent());
    }
}
