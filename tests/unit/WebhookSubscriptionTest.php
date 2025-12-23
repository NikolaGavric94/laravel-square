<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;

class WebhookSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Webhook subscription creation.
     *
     * @return void
     */
    public function test_webhook_subscription_make()
    {
        $subscription = factory(WebhookSubscription::class)->make([
            'square_id' => 'test-subscription-id',
            'name' => 'Test Webhook',
            'notification_url' => 'https://example.com/webhook',
            'event_types' => ['order.created', 'order.updated'],
            'api_version' => '2024-06-04',
            'signature_key' => 'test-signature-key',
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertNotNull($subscription, 'Subscription is null.');
    }

    /**
     * Webhook subscription persistence.
     *
     * @return void
     */
    public function test_webhook_subscription_create()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'square_id' => 'test-subscription-id',
            'name' => 'Test Webhook',
            'notification_url' => 'https://example.com/webhook',
            'event_types' => ['order.created', 'order.updated'],
            'api_version' => '2024-06-04',
            'signature_key' => 'test-signature-key',
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'square_id' => 'test-subscription-id',
            'name' => 'Test Webhook',
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertEquals(['order.created', 'order.updated'], $subscription->event_types);
        $this->assertTrue($subscription->is_enabled);
        $this->assertTrue($subscription->is_active);
    }

    /**
     * Test fillable attributes.
     *
     * @return void
     */
    public function test_webhook_subscription_fillable_attributes()
    {
        $data = [
            'square_id' => 'test-id',
            'name' => 'Test Webhook',
            'notification_url' => 'https://example.com/webhook',
            'event_types' => ['order.created'],
            'api_version' => '2024-06-04',
            'signature_key' => 'test-key',
            'is_enabled' => true,
            'is_active' => true,
            'last_tested_at' => now(),
            'last_failed_at' => now(),
            'last_error' => 'Test error',
        ];

        $subscription = WebhookSubscription::create($data);

        foreach ($data as $key => $value) {
            if (in_array($key, ['last_tested_at', 'last_failed_at'])) {
                $this->assertInstanceOf(Carbon::class, $subscription->$key);
            } else {
                $this->assertEquals($value, $subscription->$key);
            }
        }
    }

    /**
     * Test casts.
     *
     * @return void
     */
    public function test_webhook_subscription_casts_work_correctly()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'event_types' => ['order.created', 'order.updated'],
            'is_enabled' => true,
            'is_active' => false,
            'last_tested_at' => '2024-06-24 10:00:00',
            'last_failed_at' => '2024-06-24 11:00:00',
        ]);

        $this->assertIsArray($subscription->event_types);
        $this->assertIsBool($subscription->is_enabled);
        $this->assertIsBool($subscription->is_active);
        $this->assertInstanceOf(Carbon::class, $subscription->last_tested_at);
        $this->assertInstanceOf(Carbon::class, $subscription->last_failed_at);
    }

    /**
     * Test webhook subscription has many events relationship.
     *
     * @return void
     */
    public function test_webhook_subscription_has_many_events_relationship()
    {
        $subscription = factory(WebhookSubscription::class)->create();

        // Create some webhook events for this subscription
        $events = factory(WebhookEvent::class, 3)->create([
            'webhook_subscription_id' => $subscription->id,
        ]);

        $this->assertCount(3, $subscription->events);
        $this->assertInstanceOf(WebhookEvent::class, $subscription->events->first());

        foreach ($events as $event) {
            $this->assertTrue($subscription->events->contains($event));
        }
    }

    /**
     * Test webhook subscription scopes.
     *
     * @return void
     */
    public function test_webhook_subscription_enabled_scope()
    {
        factory(WebhookSubscription::class, 3)->create(['is_enabled' => true]);
        factory(WebhookSubscription::class, 2)->create(['is_enabled' => false]);

        $enabledSubscriptions = WebhookSubscription::enabled()->get();

        $this->assertCount(3, $enabledSubscriptions);
        foreach ($enabledSubscriptions as $subscription) {
            $this->assertTrue($subscription->is_enabled);
        }
    }

    /**
     * Test webhook subscription active scope.
     *
     * @return void
     */
    public function test_webhook_subscription_active_scope()
    {
        factory(WebhookSubscription::class, 4)->create(['is_active' => true]);
        factory(WebhookSubscription::class, 1)->create(['is_active' => false]);

        $activeSubscriptions = WebhookSubscription::active()->get();

        $this->assertCount(4, $activeSubscriptions);
        foreach ($activeSubscriptions as $subscription) {
            $this->assertTrue($subscription->is_active);
        }
    }

    /**
     * Test webhook subscription forEventType scope.
     *
     * @return void
     */
    public function test_webhook_subscription_for_event_type_scope()
    {
        factory(WebhookSubscription::class)->create([
            'event_types' => ['order.created', 'order.updated'],
        ]);
        factory(WebhookSubscription::class)->create([
            'event_types' => ['order.fulfillment.updated'],
        ]);
        factory(WebhookSubscription::class)->create([
            'event_types' => ['order.created', 'customer.created'],
        ]);

        $orderCreatedSubscriptions = WebhookSubscription::forEventType('order.created')->get();
        $orderFulfillmentSubscriptions = WebhookSubscription::forEventType('order.fulfillment.updated')->get();
        $customerCreatedSubscriptions = WebhookSubscription::forEventType('customer.created')->get();

        $this->assertCount(2, $orderCreatedSubscriptions);
        $this->assertCount(1, $orderFulfillmentSubscriptions);
        $this->assertCount(1, $customerCreatedSubscriptions);
    }

    /**
     * Test handlesEventType method.
     *
     * @return void
     */
    public function test_webhook_subscription_handles_event_type_method()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'event_types' => ['order.created', 'order.updated', 'order.fulfillment.updated'],
        ]);

        $this->assertTrue($subscription->handlesEventType('order.created'));
        $this->assertTrue($subscription->handlesEventType('order.updated'));
        $this->assertTrue($subscription->handlesEventType('order.fulfillment.updated'));
        $this->assertFalse($subscription->handlesEventType('customer.created'));
        $this->assertFalse($subscription->handlesEventType('payment.created'));
    }

    /**
     * Test handlesEventType method with null event_types.
     *
     * @return void
     */
    public function test_webhook_subscription_handles_event_type_with_null_event_types()
    {
        $subscription = new WebhookSubscription();
        $subscription->event_types = null;

        $this->assertFalse($subscription->handlesEventType('order.created'));
    }

    /**
     * Test isOperational method.
     *
     * @return void
     */
    public function test_webhook_subscription_is_operational_method()
    {
        $operationalSubscription = factory(WebhookSubscription::class)->create([
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $disabledSubscription = factory(WebhookSubscription::class)->create([
            'is_enabled' => false,
            'is_active' => true,
        ]);

        $inactiveSubscription = factory(WebhookSubscription::class)->create([
            'is_enabled' => true,
            'is_active' => false,
        ]);

        $completelyOffSubscription = factory(WebhookSubscription::class)->create([
            'is_enabled' => false,
            'is_active' => false,
        ]);

        $this->assertTrue($operationalSubscription->isOperational());
        $this->assertFalse($disabledSubscription->isOperational());
        $this->assertFalse($inactiveSubscription->isOperational());
        $this->assertFalse($completelyOffSubscription->isOperational());
    }

    /**
     * Test markAsTested method.
     *
     * @return void
     */
    public function test_webhook_subscription_mark_as_tested_method()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'last_tested_at' => null,
            'last_error' => 'Previous error',
        ]);

        $this->assertNull($subscription->last_tested_at);
        $this->assertEquals('Previous error', $subscription->last_error);

        $result = $subscription->markAsTested();

        $this->assertTrue($result);
        $subscription->refresh();
        $this->assertNotNull($subscription->last_tested_at);
        $this->assertNull($subscription->last_error);
        $this->assertInstanceOf(Carbon::class, $subscription->last_tested_at);
    }

    /**
     * Test markAsFailed method.
     *
     * @return void
     */
    public function test_webhook_subscription_mark_as_failed_method()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'last_failed_at' => null,
            'last_error' => null,
        ]);

        $errorMessage = 'Webhook endpoint returned 500 error';
        $result = $subscription->markAsFailed($errorMessage);

        $this->assertTrue($result);
        $subscription->refresh();
        $this->assertNotNull($subscription->last_failed_at);
        $this->assertEquals($errorMessage, $subscription->last_error);
        $this->assertInstanceOf(Carbon::class, $subscription->last_failed_at);
    }

    /**
     * Test chaining multiple scopes.
     *
     * @return void
     */
    public function test_webhook_subscription_multiple_scopes_can_be_chained()
    {
        factory(WebhookSubscription::class)->create([
            'is_enabled' => true,
            'is_active' => true,
            'event_types' => ['order.created', 'order.updated'],
        ]);

        factory(WebhookSubscription::class)->create([
            'is_enabled' => true,
            'is_active' => false,
            'event_types' => ['order.created'],
        ]);

        factory(WebhookSubscription::class)->create([
            'is_enabled' => false,
            'is_active' => true,
            'event_types' => ['order.created', 'customer.created'],
        ]);

        $operationalOrderSubscriptions = WebhookSubscription::enabled()
            ->active()
            ->forEventType('order.created')
            ->get();

        $this->assertCount(1, $operationalOrderSubscriptions);
        $first = $operationalOrderSubscriptions->first();
        $this->assertTrue($first->is_enabled);
        $this->assertTrue($first->is_active);
        $this->assertTrue($first->handlesEventType('order.created'));
    }

    /**
     * Test factory generates realistic data.
     *
     * @return void
     */
    public function test_webhook_subscription_factory_generates_realistic_data()
    {
        $subscription = factory(WebhookSubscription::class)->create();

        // Test square_id format
        $this->assertStringStartsWith('wh_', $subscription->square_id);

        // Test name format
        $this->assertStringContainsString('Webhook', $subscription->name);

        // Test notification_url format
        $this->assertStringStartsWith('https://', $subscription->notification_url);
        $this->assertStringContainsString('/webhook/', $subscription->notification_url);

        // Test signature_key format
        $this->assertStringStartsWith('wh_key_', $subscription->signature_key);

        // Test event_types is valid array
        $this->assertIsArray($subscription->event_types);
        $this->assertNotEmpty($subscription->event_types);

        // Test api_version
        $this->assertEquals('2024-06-04', $subscription->api_version);
    }

    /**
     * Test batch operations on webhook subscriptions.
     *
     * @return void
     */
    public function test_webhook_subscription_batch_operations()
    {
        // Create multiple subscriptions
        $subscriptions = factory(WebhookSubscription::class, 5)->create();

        $this->assertCount(5, $subscriptions);
        $this->assertCount(5, WebhookSubscription::all());

        // Test batch updates
        WebhookSubscription::whereIn('id', $subscriptions->pluck('id'))
            ->update(['is_enabled' => false]);

        $disabledCount = WebhookSubscription::where('is_enabled', false)->count();
        $this->assertEquals(5, $disabledCount);
    }

    /**
     * Test getWebhookBuilder method returns correctly configured builder.
     *
     * @return void
     */
    public function test_webhook_subscription_get_webhook_builder_method()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'name' => 'Test Webhook Builder',
            'notification_url' => 'https://example.com/webhook-builder',
            'event_types' => ['order.created', 'payment.updated'],
            'api_version' => '2024-06-04',
            'is_enabled' => true,
        ]);

        $builder = $subscription->getWebhookBuilder();

        $this->assertInstanceOf(WebhookBuilder::class, $builder);
        $this->assertEquals('Test Webhook Builder', $builder->getName());
        $this->assertEquals('https://example.com/webhook-builder', $builder->getNotificationUrl());
        $this->assertEquals(['order.created', 'payment.updated'], $builder->getEventTypes());
        $this->assertEquals('2024-06-04', $builder->getApiVersion());
    }

    /**
     * Test getWebhookBuilder method with disabled subscription.
     *
     * @return void
     */
    public function test_webhook_subscription_get_webhook_builder_with_disabled_subscription()
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'name' => 'Disabled Webhook',
            'notification_url' => 'https://example.com/disabled-webhook',
            'event_types' => ['customer.created'],
            'api_version' => '2024-06-04',
            'is_enabled' => false,
        ]);

        $builder = $subscription->getWebhookBuilder();

        $this->assertInstanceOf(\Nikolag\Square\Builders\WebhookBuilder::class, $builder);
        $this->assertEquals('Disabled Webhook', $builder->getName());
        $this->assertEquals('https://example.com/disabled-webhook', $builder->getNotificationUrl());
        $this->assertEquals(['customer.created'], $builder->getEventTypes());
        $this->assertEquals('2024-06-04', $builder->getApiVersion());
    }

    /**
     * Test getWebhookBuilder method with null values.
     *
     * @return void
     */
    public function test_webhook_subscription_get_webhook_builder_with_null_values()
    {
        $subscription = new WebhookSubscription([
            'name' => null,
            'notification_url' => null,
            'event_types' => null,
            'api_version' => null,
            'is_enabled' => null,
        ]);

        $builder = $subscription->getWebhookBuilder();

        $this->assertInstanceOf(\Nikolag\Square\Builders\WebhookBuilder::class, $builder);
        $this->assertNull($builder->getName());
        $this->assertNull($builder->getNotificationUrl());
        $this->assertEquals([], $builder->getEventTypes());
    }
}
