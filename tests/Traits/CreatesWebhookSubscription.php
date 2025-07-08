<?php

namespace Nikolag\Square\Tests\Traits;

use Nikolag\Square\Models\WebhookSubscription;

/**
 * Square API mocking trait based on dependency injection pattern.
 *
 */
trait CreatesWebhookSubscription
{
    /**
     * Create a test webhook subscription for testing purposes.
     *
     * @return WebhookSubscription
     */
    protected function createTestWebhookSubscription(): WebhookSubscription
    {
        return WebhookSubscription::create([
            'square_id' => 'test_webhook_subscription',
            'name' => 'Test Webhook Subscription',
            'notification_url' => 'https://example.com/webhook',
            'event_types' => ['order.created', 'order.updated', 'payment.created'],
            'api_version' => '2023-10-18',
            'signature_key' => 'test_signature_key',
            'is_enabled' => true,
            'is_active' => true,
        ]);
    }
}
