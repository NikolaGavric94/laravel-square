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
}
