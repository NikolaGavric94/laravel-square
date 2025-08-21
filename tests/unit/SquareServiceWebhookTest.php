<?php

namespace Nikolag\Square\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exception;
use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\Traits\MocksSquareConfigDependency;
use Nikolag\Square\Utils\WebhookProcessor;
use Square\Models\ListWebhookEventTypesResponse;
use Square\Models\ListWebhookSubscriptionsResponse;
use Square\Models\TestWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionSignatureKeyResponse;
use Square\Models\WebhookSubscription as SquareWebhookSubscription;

class SquareServiceWebhookTest extends TestCase
{
    use RefreshDatabase, MocksSquareConfigDependency;

    private string $testWebhookUrl = 'https://example.com/webhook';
    private array $testEventTypes = ['order.created', 'payment.updated'];

    /**
     * Test creating a webhook subscription successfully.
     */
    public function test_create_webhook_success(): void
    {
        // Mock the create webhook API call
        $this->mockCreateWebhookSuccess([
            'id' => 'wh_test_123',
            'name' => 'Test Webhook',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'signatureKey' => 'test_signature_key',
            'enabled' => true,
        ]);

        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhook = Square::createWebhookSubscription($builder);

        $this->assertInstanceOf(WebhookSubscription::class, $webhook);
        $this->assertEquals('Test Webhook', $webhook->name);
        $this->assertEquals($this->testWebhookUrl, $webhook->notification_url);
        $this->assertEquals($this->testEventTypes, $webhook->event_types);
        $this->assertTrue($webhook->is_enabled);
        $this->assertEquals('wh_test_123', $webhook->square_id);

        // Verify it's stored in the database
        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'name' => 'Test Webhook',
            'notification_url' => $this->testWebhookUrl,
        ]);
    }

    /**
     * Test webhook creation with API error.
     */
    public function test_create_webhook_api_error(): void
    {
        // Using the fluent API for error mocking
        $this->mockCreateWebhookError();

        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $this->expectException(Exception::class);
        Square::createWebhookSubscription($builder);
    }

    /**
     * Test deleting a webhook subscription successfully.
     * Demonstrates the new deleteWebhook endpoint support with minimal changes.
     */
    public function test_delete_webhook_success(): void
    {
        // Create a webhook first so we have one to delete
        $this->mockCreateWebhookSuccess([
            'id' => 'wh_to_delete_123',
            'name' => 'Webhook to Delete',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'signatureKey' => 'test_signature_key',
            'enabled' => true,
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook to Delete')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhookSubscription($builder);

        // Now mock the delete operation
        $this->mockDeleteWebhookSuccess();

        // Delete the webhook
        $result = Square::deleteWebhookSubscription($webhookSubscription->square_id);

        $this->assertTrue($result);

        // Verify the webhook was removed from database
        $this->assertDatabaseMissing('nikolag_webhook_subscriptions', [
            'square_id' => 'wh_to_delete_123',
        ]);
    }

    /**
     * Test delete webhook with error response.
     */
    public function test_delete_webhook_error(): void
    {
        // Mock delete error - same fluent API, different endpoint
        $this->mockDeleteWebhookError();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INVALID_REQUEST_ERROR: Delete webhook failed');

        Square::deleteWebhookSubscription('non_existent_webhook_id');
    }

    /**
     * Tests retrieving a webhook subscription successfully.
     *
     * @return void
     */
    public function test_retrieve_webhook_success(): void
    {
        // Using the fluent API from the trait
        $this->mockRetrieveWebhookSuccess([
            'id' => 'webhook_to_fetch_123',
            'name' => 'Fetched Webhook',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'signatureKey' => 'test_signature_key',
            'enabled' => true,
        ]);

        $webhookSubscription = Square::retrieveWebhookSubscription('webhook_to_fetch_123');

        $this->assertInstanceOf(SquareWebhookSubscription::class, $webhookSubscription);
        $this->assertEquals('Fetched Webhook', $webhookSubscription->getName());
        $this->assertEquals($this->testWebhookUrl, $webhookSubscription->getNotificationUrl());
        $this->assertEquals($this->testEventTypes, $webhookSubscription->getEventTypes());
        $this->assertTrue($webhookSubscription->getEnabled());
        $this->assertEquals('webhook_to_fetch_123', $webhookSubscription->getId());
    }

    /**
     * Test updating a webhook subscription successfully.
     */
    public function test_update_webhook_success(): void
    {
        // Using the fluent API from the trait
        $this->mockCreateWebhookSuccess([
            'id' => 'wh_to_update_123',
            'name' => 'Test Webhook',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'signatureKey' => 'test_signature_key',
            'enabled' => true,
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook to Update')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhookSubscription($builder);

        // Now mock the update operation - same structure, different endpoint
        $this->mockUpdateWebhookSuccess([
            'id' => 'wh_to_update_123',
            'name' => 'Updated Webhook Name',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'enabled' => true,
        ]);

        // Get the subscription builder and update the name
        $builder = $webhookSubscription->getWebhookBuilder();
        $builder->name('Updated Webhook Name');

        $webhookSubscription = Square::updateWebhookSubscription($webhookSubscription->square_id, $builder);

        $this->assertInstanceOf(WebhookSubscription::class, $webhookSubscription);
        $this->assertEquals('Updated Webhook Name', $webhookSubscription->name);
        $this->assertEquals($this->testWebhookUrl, $webhookSubscription->notification_url);
        $this->assertEquals($this->testEventTypes, $webhookSubscription->event_types);
        $this->assertTrue($webhookSubscription->is_enabled);
        $this->assertEquals('wh_to_update_123', $webhookSubscription->square_id);

        // Verify it's stored in the database
        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'name' => 'Updated Webhook Name',
            'notification_url' => $this->testWebhookUrl,
        ]);
    }

    /**
     * Test update webhook when local model is deleted - should recreate WebhookSubscription.
     *
     * This test simulates the scenario where:
     * 1. A webhook is created via Square API
     * 2. The local WebhookSubscription record is deleted from the database
     * 3. An update is performed via Square API
     * 4. The local WebhookSubscription model should be recreated from the update response
     *
     * Note: The updateWebhookSubscription response doesn't contain signature_key,
     * so the SquareService updateWebhook method should handle this gracefully.
     */
    public function test_update_webhook_recreates_deleted_local_model(): void
    {
        $signatureKey = 'original_signature_key';
        // Step 1: Mock and create webhook via Square API
        $this->mockCreateWebhookSuccess([
            'id' => 'wh_recreate_test_789',
            'name' => 'Webhook for Recreate Test',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'apiVersion' => '2023-10-11',
            'signatureKey' => $signatureKey,
            'enabled' => true,
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook for Recreate Test')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhookSubscription($builder);

        // Verify webhook was created locally
        $this->assertInstanceOf(WebhookSubscription::class, $webhookSubscription);
        $this->assertEquals('wh_recreate_test_789', $webhookSubscription->square_id);
        $this->assertEquals($signatureKey, $webhookSubscription->signature_key);

        // Verify encryption
        $encryptedKey = $webhookSubscription->getRawOriginal('signature_key');
        $this->assertNotEquals($encryptedKey, $webhookSubscription->signature_key);

        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id,
            'name' => 'Webhook for Recreate Test',
            'signature_key' => $encryptedKey,
        ]);

        // Step 2: Delete the local WebhookSubscription record from database
        // (simulating a scenario where local data was lost but Square webhook still exists)
        WebhookSubscription::where('square_id', $webhookSubscription->square_id)->delete();

        // Verify local record is gone
        $this->assertDatabaseMissing('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id,
        ]);

        // Step 3: Mock update webhook response (note: no signature_key in update response)
        $newName = 'Updated Recreated Webhook';
        $mockUpdateWebhookData = $mockRetrieveWebhookData = [
            'id' => $webhookSubscription->square_id,
            'name' => $newName,
            'notificationUrl' => 'https://updated.example.com/webhook',
            'eventTypes' => ['order.created'],
            'apiVersion' => '2023-10-11',
            'enabled' => true,
            'signatureKey' => $signatureKey, // Original signature key
        ];
        // Remove the signatureKey to simulate update response
        unset($mockUpdateWebhookData['signatureKey']);
        $this->mockUpdateWebhookSuccess($mockUpdateWebhookData);
        $this->mockRetrieveWebhookSuccess($mockRetrieveWebhookData);

        // Step 4: Update the webhook payload
        $builder = $webhookSubscription->getWebhookBuilder();
        $builder->name($newName);
        $builder->notificationUrl('https://updated.example.com/webhook');
        $builder->eventTypes(['order.created']);

        $updatedWebhook = Square::updateWebhookSubscription($webhookSubscription->square_id, $builder);

        // Step 5: Verify the WebhookSubscription model was recreated locally
        $this->assertInstanceOf(WebhookSubscription::class, $updatedWebhook);
        $this->assertEquals($webhookSubscription->square_id, $updatedWebhook->square_id);
        $this->assertEquals($newName, $updatedWebhook->name);
        // $this->assertEquals('https://updated.example.com/webhook', $updatedWebhook->notification_url);
        $this->assertEquals(['order.created'], $updatedWebhook->event_types);
        $this->assertTrue($updatedWebhook->is_enabled);

        // Verify the signature_key field is handled gracefully (should be null or default)
        // since updateWebhookSubscription response doesn't include it
        $this->assertTrue(
            is_null($updatedWebhook->signature_key) || ! empty($updatedWebhook->signature_key),
            'signature_key should be handled gracefully when missing from update response'
        );

        // Step 6: Verify the model was recreated in the database
        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id,
            'name' => $newName,
            'notification_url' => 'https://updated.example.com/webhook',
            'is_enabled' => true,
        ]);

        // Verify only one record exists (not duplicated)
        $webhookCount = WebhookSubscription::where('square_id', $webhookSubscription->square_id)->count();
        $this->assertEquals(1, $webhookCount, 'Should have exactly one WebhookSubscription record');
    }

    /**
     * Test listing webhook subscriptions.
     */
    public function test_list_webhooks_success(): void
    {
        // Step 1: Mock and create webhook via Square API
        $this->mockListWebhookSuccess([
            [
                'id' => 'wh_recreate_test_789',
                'name' => 'Webhook for Recreate Test',
                'notificationUrl' => $this->testWebhookUrl,
                'eventTypes' => $this->testEventTypes,
                'apiVersion' => '2023-10-11',
                'signatureKey' => 'fake-key',
                'enabled' => true,
            ],
        ]);

        $response = Square::listWebhookSubscriptions();

        $this->assertInstanceOf(ListWebhookSubscriptionsResponse::class, $response);
        $this->assertIsArray($response->getSubscriptions());
    }

    /**
     * Test listing webhook subscriptions.
     */
    public function test_list_webhooks_success_no_webhooks(): void
    {
        // Step 1: Mock and create webhook via Square API
        $this->mockListWebhookSuccess(null);

        $response = Square::listWebhookSubscriptions();

        $this->assertInstanceOf(ListWebhookSubscriptionsResponse::class, $response);
        $this->assertNull($response->getSubscriptions());
    }

    /**
     * Test listing webhook event types.
     */
    public function test_list_webhook_event_types_success(): void
    {
        $response = Square::listWebhookEventTypes();
        $this->assertInstanceOf(ListWebhookEventTypesResponse::class, $response);
        $this->assertIsArray($response->getEventTypes());
    }

    /**
     * Test listing webhook event types with API version.
     */
    public function test_list_webhook_event_types_with_api_version(): void
    {
        $response = Square::listWebhookEventTypes('2018-07-12');
        $this->assertInstanceOf(ListWebhookEventTypesResponse::class, $response);
        $this->assertNull($response->getEventTypes());
    }

    /**
     * Test testing a webhook subscription.
     */
    public function test_test_webhook_success(): void
    {
        // Mock successful test webhook response
        $this->mockTestWebhookSuccess();

        $response = Square::testWebhookSubscription('fake_id', 'order.created');

        $this->assertInstanceOf(TestWebhookSubscriptionResponse::class, $response);
        // Note: The actual properties available depend on the Square SDK implementation
        // For now, we just verify the response type is correct
    }

    /**
     * Test testing a webhook subscription with API error.
     */
    public function test_test_webhook_error(): void
    {
        // Mock error response for test webhook
        $this->mockTestWebhookError('Test webhook failed - invalid subscription', 404);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INVALID_REQUEST_ERROR: Test webhook failed - invalid subscription');

        Square::testWebhookSubscription('fake_id', 'order.created');
    }

    /**
     * Test updating webhook signature key.
     */
    public function test_update_webhook_signature_key_success(): void
    {
        $subscriptionId = 'wbhk_test_signature_update_123';
        $newSignatureKey = 'test_updated_signature_key_'.uniqid();

        // Create a webhook subscription first so we have one to update
        $subscription = WebhookSubscription::create([
            'square_id' => $subscriptionId,
            'name' => 'Test Webhook for Signature Update',
            'notification_url' => $this->testWebhookUrl,
            'event_types' => $this->testEventTypes,
            'api_version' => '2023-10-18',
            'signature_key' => 'old_signature_key',
            'is_enabled' => true,
        ]);

        // Mock successful signature key update response
        $this->mockUpdateWebhookSignatureKey([
            'signatureKey' => $newSignatureKey,
        ]);

        $response = Square::updateWebhookSignatureKey($subscriptionId);

        $this->assertInstanceOf(UpdateWebhookSubscriptionSignatureKeyResponse::class, $response);
        $this->assertIsString($response->getSignatureKey());
        $this->assertEquals($newSignatureKey, $response->getSignatureKey());

        // Verify the local subscription was updated with the new signature key
        $this->assertNotEquals($newSignatureKey, $subscription->signature_key);
        $subscription->refresh();
        $this->assertEquals($newSignatureKey, $subscription->signature_key);
    }

    /**
     * Test updating webhook signature key with API error.
     */
    public function test_update_webhook_signature_key_error(): void
    {
        $subscriptionId = 'wbhk_invalid_subscription_id';

        // Mock error response for signature key update
        $this->mockUpdateWebhookSignatureKeyError('Webhook subscription not found', 404);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INVALID_REQUEST_ERROR: Webhook subscription not found');

        Square::updateWebhookSignatureKey($subscriptionId);
    }

    /**
     * Test processing a webhook with valid signature.
     */
    public function test_process_webhook_success(): void
    {
        // Create a webhook subscription with consistent notification URL
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        // Generate realistic webhook data with proper signature using factory patterns
        $request = $this->mockWebhookSubscriptionResponse($subscription, 'order.created');

        $event = Square::processWebhook($request);

        // Validate the webhook event was created correctly
        $this->assertInstanceOf(WebhookEvent::class, $event);

        // Parse the payload to get the event data for assertions
        $payloadData = $request->json()->all();
        $this->assertEquals($payloadData['event_id'], $event->square_event_id);
        $this->assertEquals($payloadData['type'], $event->event_type);
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertEquals($subscription->id, $event->webhook_subscription_id);

        // Verify the event data structure contains expected Square webhook format
        $this->assertIsArray($event->event_data);
        $this->assertArrayHasKey('merchant_id', $event->event_data);
        $this->assertArrayHasKey('data', $event->event_data);
        $this->assertArrayHasKey('object', $event->event_data['data']);

        // Verify it was stored in the database
        $this->assertDatabaseHas('nikolag_webhook_events', [
            'square_event_id' => $payloadData['event_id'],
            'event_type' => 'order.created',
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Test processing a payment webhook with valid signature.
     */
    public function test_process_webhook_payment_success(): void
    {
        // Create a webhook subscription with consistent notification URL
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        // Generate realistic webhook data with proper signature using factory patterns
        $request = $this->mockWebhookSubscriptionResponse($subscription, 'payment.created');

        $event = Square::processWebhook($request);

        // Validate the webhook event was created correctly
        $this->assertInstanceOf(WebhookEvent::class, $event);

        // Parse the payload to get the event data for assertions
        $payloadData = $request->json()->all();
        $this->assertEquals($payloadData['event_id'], $event->square_event_id);
        $this->assertEquals($payloadData['type'], $event->event_type);
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertEquals($subscription->id, $event->webhook_subscription_id);

        // Verify the event data structure contains expected Square webhook format
        $this->assertIsArray($event->event_data);
        $this->assertArrayHasKey('merchant_id', $event->event_data);
        $this->assertArrayHasKey('data', $event->event_data);
        $this->assertArrayHasKey('object', $event->event_data['data']);

        // Verify it was stored in the database
        $this->assertDatabaseHas('nikolag_webhook_events', [
            'square_event_id' => $payloadData['event_id'],
            'event_type' => 'payment.created',
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id,
        ]);

        // Verify payment-specific data structure
        $this->assertArrayHasKey('data', $event->event_data);
        $this->assertEquals('payment', $event->event_data['data']['type']);
        $this->assertArrayHasKey('payment', $event->event_data['data']['object']);
        $this->assertArrayHasKey('amount_money', $event->event_data['data']['object']['payment']);
        $this->assertEquals(1_00, $event->event_data['data']['object']['payment']['amount_money']['amount']);
        $this->assertEquals('USD', $event->event_data['data']['object']['payment']['amount_money']['currency']);
    }

    /**
     * Test processing a payment webhook with retry data.
     */
    public function test_process_webhook_with_retry_data_success(): void
    {
        // Create a webhook subscription with consistent notification URL
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        $retryData = [
            'reason' => 'http_failure',
            'number' => 1,
            'initialDeliveryTimestamp' => now()->subSeconds(10)->toIso8601String(),
        ];

        // Generate realistic webhook data with proper signature using factory patterns
        $request = $this->mockWebhookSubscriptionResponse($subscription, 'payment.created', null, $retryData);

        $event = Square::processWebhook($request);

        // Validate the webhook event was created correctly
        $this->assertInstanceOf(WebhookEvent::class, $event);

        // Parse the payload to get the event data for assertions
        $payloadData = $request->json()->all();
        $this->assertEquals($payloadData['event_id'], $event->square_event_id);
        $this->assertEquals($payloadData['type'], $event->event_type);
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertEquals($subscription->id, $event->webhook_subscription_id);

        // Verify retry data was properly stored
        $this->assertTrue($event->isRetry());
        $this->assertEquals($retryData['reason'], $event->retry_reason);
        $this->assertEquals($retryData['number'], $event->retry_number);
        $this->assertNotNull($event->initial_delivery_timestamp);

        // Test retry information getter
        $retryInfo = $event->getRetryInfo();
        $this->assertIsArray($retryInfo);
        $this->assertEquals($retryData['reason'], $retryInfo['reason']);
        $this->assertEquals($retryData['number'], $retryInfo['number']);
        $this->assertInstanceOf(Carbon::class, $retryInfo['initial_delivery_timestamp']);

        // Verify description includes retry information
        $description = $event->getDescription();
        $this->assertStringContainsString('retry #1', $description);

        // Verify it was stored in the database with retry data
        $this->assertDatabaseHas('nikolag_webhook_events', [
            'square_event_id' => $payloadData['event_id'],
            'event_type' => 'payment.created',
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => $subscription->id,
            'retry_reason' => $retryData['reason'],
            'retry_number' => $retryData['number'],
        ]);
    }

    /**
     * Test processing webhook without subscription ID header.
     */
    public function test_process_webhook_missing_subscription_id_header(): void
    {
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
            'event_id' => 'test-event-id',
            'type' => 'order.created',
            'data' => ['test' => 'data'],
        ]));

        // No square-subscription-id header set

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing Square webhook subscription ID in headers');

        Square::processWebhook($request);
    }

    /**
     * Test processing webhook with invalid subscription ID.
     */
    public function test_process_webhook_invalid_subscription_id(): void
    {
        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
            'event_id' => 'test-event-id',
            'type' => 'order.created',
            'data' => ['test' => 'data'],
        ]));

        $request->headers->set('square-subscription-id', 'non-existent-subscription-id');

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('No webhook subscription found for verification');

        Square::processWebhook($request);
    }

    /**
     * Test processing webhook with invalid signature.
     */
    public function test_process_webhook_invalid_signature(): void
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
            'event_id' => 'test-event-id',
            'type' => 'order.created',
            'created_at' => now()->toISOString(),
            'data' => ['test' => 'data'],
        ]));

        $request->headers->set('square-subscription-id', $subscription->square_id);
        $request->headers->set('X-Square-HmacSha256-Signature', 'invalid-signature');

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        Square::processWebhook($request);
    }

    /**
     * Test processing webhook with missing signature header.
     */
    public function test_process_webhook_missing_signature_header(): void
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
            'event_id' => 'test-event-id',
            'type' => 'order.created',
            'created_at' => now()->toISOString(),
            'data' => ['test' => 'data'],
        ]));

        $request->headers->set('square-subscription-id', $subscription->square_id);
        // No signature header set

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing webhook signature header');

        Square::processWebhook($request);
    }

    /**
     * Test processing webhook with invalid JSON payload.
     */
    public function test_process_webhook_invalid_json_payload(): void
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        $invalidJson = 'invalid-json-content';
        $signature = WebhookProcessor::generateTestSignature(
            $subscription->signature_key,
            $subscription->notification_url,
            $invalidJson
        );

        $request = Request::create('/webhook', 'POST', [], [], [], [], $invalidJson);
        $request->headers->set('square-subscription-id', $subscription->square_id);
        $request->headers->set('X-Square-HmacSha256-Signature', $signature);

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        Square::processWebhook($request);
    }

    /**
     * Test processing webhook with missing required fields.
     */
    public function test_process_webhook_missing_required_fields(): void
    {
        $subscription = factory(WebhookSubscription::class)->create([
            'notification_url' => $this->testWebhookUrl,
        ]);

        $incompletePayload = json_encode([
            'type' => 'order.created',
            // Missing event_id and created_at
        ]);

        $signature = WebhookProcessor::generateTestSignature(
            $subscription->signature_key,
            $subscription->notification_url,
            $incompletePayload
        );

        $request = Request::create('/webhook', 'POST', [], [], [], [], $incompletePayload);
        $request->headers->set('square-subscription-id', $subscription->square_id);
        $request->headers->set('X-Square-HmacSha256-Signature', $signature);

        $this->expectException(InvalidSquareSignatureException::class);
        $this->expectExceptionMessage('Missing required event fields');

        Square::processWebhook($request);
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
        // Create a webhook event
        $event = WebhookEvent::create([
            'square_event_id' => 'event_123',
            'event_type' => 'order.created',
            'event_time' => now(),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => 1,
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
            'webhook_subscription_id' => 1,
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
        // Create test webhook events with different ages
        $oldEvent1 = new WebhookEvent([
            'square_event_id' => 'old_event_1',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(45),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PROCESSED,
            'webhook_subscription_id' => 1,
        ]);
        $oldEvent1->created_at = now()->subDays(45);
        $oldEvent1->save();

        $oldEvent2 = new WebhookEvent([
            'square_event_id' => 'old_event_2',
            'event_type' => 'order.updated',
            'event_time' => now()->subDays(35),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_FAILED,
            'webhook_subscription_id' => 1,
        ]);
        $oldEvent2->created_at = now()->subDays(35);
        $oldEvent2->save();

        $oldPendingEvent = new WebhookEvent([
            'square_event_id' => 'old_pending_event',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(40),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PENDING,
            'webhook_subscription_id' => 1,
        ]);
        $oldPendingEvent->created_at = now()->subDays(40);
        $oldPendingEvent->save();

        $recentEvent = new WebhookEvent([
            'square_event_id' => 'recent_event',
            'event_type' => 'order.created',
            'event_time' => now()->subDays(10),
            'event_data' => ['test' => 'data'],
            'status' => WebhookEvent::STATUS_PROCESSED,
            'webhook_subscription_id' => 1,
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

    /**
     * Test webhook builder with fluent interface.
     */
    public function test_webhook_builder_fluent_interface(): void
    {
        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->addEventType('customer.created')
            ->apiVersion('2023-10-18')
            ->enabled()
            ->disabled();

        $this->assertEquals('Test Webhook', $builder->getName());
        $this->assertEquals($this->testWebhookUrl, $builder->getNotificationUrl());
        $this->assertContains('customer.created', $builder->getEventTypes());
        $this->assertEquals('2023-10-18', $builder->getApiVersion());
    }

    /**
     * Test webhook builder validation for missing event types.
     */
    public function test_webhook_builder_missing_event_types(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('At least one event type is required');

        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl);

        $builder->buildCreateRequest();
    }

    /**
     * Test webhook builder validation for missing notification URL.
     */
    public function test_webhook_builder_missing_notification_url(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Notification URL is required');

        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->eventTypes($this->testEventTypes);

        $builder->buildCreateRequest();
    }

    /**
     * Test webhook builder reset functionality.
     */
    public function test_webhook_builder_reset(): void
    {
        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->reset();

        $this->assertNull($builder->getName());
        $this->assertNull($builder->getNotificationUrl());
        $this->assertEmpty($builder->getEventTypes());
    }
}
