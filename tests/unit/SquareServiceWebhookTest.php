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
use Nikolag\Square\Tests\Traits\MocksSquareApi;

class SquareServiceWebhookTest extends TestCase
{
    use RefreshDatabase, MocksSquareApi;

    private string $testWebhookUrl = 'https://example.com/webhook';
    private array $testEventTypes = ['order.created', 'payment.updated'];

    /**
     * Test creating a webhook subscription successfully.
     */
    public function test_create_webhook_success(): void
    {
        // Using the fluent API from the trait
        $this->mockWebhook('createWebhookSubscription')
            ->withSuccess([
                'id' => 'wh_test_123',
                'name' => 'Test Webhook',
                'notificationUrl' => $this->testWebhookUrl,
                'eventTypes' => $this->testEventTypes,
                'apiVersion' => '2023-10-11',
                'signatureKey' => 'test_signature_key',
                'enabled' => true
            ])
            ->apply();

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
        $this->mockWebhook('createWebhookSubscription')
            ->withError('Invalid webhook configuration', 422)
            ->apply();

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
        $this->mockWebhook('createWebhookSubscription')
            ->withSuccess([
                'id' => 'wh_to_delete_123',
                'name' => 'Webhook to Delete',
                'notificationUrl' => $this->testWebhookUrl,
                'eventTypes' => $this->testEventTypes,
                'enabled' => true
            ])
            ->apply();

        $builder = Square::webhookBuilder()
            ->name('Webhook to Delete')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        $webhookSubscription = Square::createWebhook($builder);

        // Now mock the delete operation - same structure, different endpoint
        $this->mockWebhook('deleteWebhookSubscription')
            ->withSuccess()  // Delete responses are typically empty
            ->apply();

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
        $this->mockWebhook('deleteWebhookSubscription')
            ->withError('Webhook not found', 404)
            ->apply();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INVALID_REQUEST_ERROR: Webhook not found');

        Square::deleteWebhook('non_existent_webhook_id');
    }
}
