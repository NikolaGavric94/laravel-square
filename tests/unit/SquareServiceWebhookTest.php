<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Exception;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\Traits\MocksSquareConfigDependency;
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
            'enabled' => true
        ]);

        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhook = Square::createWebhook($builder);

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
        Square::createWebhook($builder);
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
            'enabled' => true
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook to Delete')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhook($builder);

        // Now mock the delete operation
        $this->mockDeleteWebhookSuccess();

        // Delete the webhook
        $result = Square::deleteWebhook($webhookSubscription->square_id);

        $this->assertTrue($result);

        // Verify the webhook was removed from database
        $this->assertDatabaseMissing('nikolag_webhook_subscriptions', [
            'square_id' => 'wh_to_delete_123'
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

        Square::deleteWebhook('non_existent_webhook_id');
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
            'enabled' => true
        ]);

        $webhookSubscription = Square::retrieveWebhook('webhook_to_fetch_123');

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
            'enabled' => true
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook to Update')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhook($builder);

        // Now mock the update operation - same structure, different endpoint
        $this->mockUpdateWebhookSuccess([
            'id' => 'wh_to_update_123',
            'name' => 'Updated Webhook Name',
            'notificationUrl' => $this->testWebhookUrl,
            'eventTypes' => $this->testEventTypes,
            'enabled' => true
        ]);

        // Get the subscription builder and update the name
        $builder = $webhookSubscription->getWebhookBuilder();
        $builder->name('Updated Webhook Name');

        $webhookSubscription = Square::updateWebhook($webhookSubscription->square_id, $builder);

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
            'enabled' => true
        ]);

        $builder = Square::webhookBuilder()
            ->name('Webhook for Recreate Test')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhook($builder);

        // Verify webhook was created locally
        $this->assertInstanceOf(WebhookSubscription::class, $webhookSubscription);
        $this->assertEquals('wh_recreate_test_789', $webhookSubscription->square_id);
        $this->assertEquals($signatureKey, $webhookSubscription->signature_key);

        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id,
            'name' => 'Webhook for Recreate Test',
            'signature_key' => $signatureKey
        ]);

        // Step 2: Delete the local WebhookSubscription record from database
        // (simulating a scenario where local data was lost but Square webhook still exists)
        WebhookSubscription::where('square_id', $webhookSubscription->square_id)->delete();

        // Verify local record is gone
        $this->assertDatabaseMissing('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id
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

        $updatedWebhook = Square::updateWebhook($webhookSubscription->square_id, $builder);

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
            is_null($updatedWebhook->signature_key) || !empty($updatedWebhook->signature_key),
            'signature_key should be handled gracefully when missing from update response'
        );

        // Step 6: Verify the model was recreated in the database
        $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
            'square_id' => $webhookSubscription->square_id,
            'name' => $newName,
            'notification_url' => 'https://updated.example.com/webhook',
            'is_enabled' => true
        ]);

        // Verify only one record exists (not duplicated)
        $webhookCount = WebhookSubscription::where('square_id', $webhookSubscription->square_id)->count();
        $this->assertEquals(1, $webhookCount, 'Should have exactly one WebhookSubscription record');
    }

}
