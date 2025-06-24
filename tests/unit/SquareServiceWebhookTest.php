<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;
use Square\Exceptions\ApiException;
use Square\Models\ListWebhookEventTypesResponse;
use Square\Models\ListWebhookSubscriptionsResponse;
use Square\Models\TestWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionSignatureKeyResponse;

class SquareServiceWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $testWebhookUrl = 'https://example.com/webhook';
    private array $testEventTypes = ['order.created', 'payment.updated'];

    /**
     * Test creating a webhook subscription successfully.
     */
    public function test_create_webhook_success(): void
    {
        $builder = Square::webhookBuilder()
            ->name('Test Webhook')
            ->notificationUrl($this->testWebhookUrl)
            ->eventTypes($this->testEventTypes)
            ->enabled();

        try {
            $webhook = Square::createWebhook($builder);

            $this->assertInstanceOf(WebhookSubscription::class, $webhook);
            $this->assertEquals('Test Webhook', $webhook->name);
            $this->assertEquals($this->testWebhookUrl, $webhook->notification_url);
            $this->assertEquals($this->testEventTypes, $webhook->event_types);
            $this->assertTrue($webhook->is_enabled);
            $this->assertNotNull($webhook->square_id);

            // Verify it's stored in the database
            $this->assertDatabaseHas('nikolag_webhook_subscriptions', [
                'name' => 'Test Webhook',
                'notification_url' => $this->testWebhookUrl,
            ]);
        } catch (ApiException $e) {
            // If we get an API exception, it might be due to sandbox limitations
            // In this case, we'll skip the test
            $this->markTestSkipped('Square API not available for webhook creation: ' . $e->getMessage());
        }
    }
}
