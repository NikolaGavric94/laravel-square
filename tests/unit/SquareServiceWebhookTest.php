<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\Tests\TestCase;

class SquareServiceWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test creating a webhook builder instance.
     */
    public function test_webhook_builder_creation(): void
    {
        $builder = Square::webhookBuilder();

        $this->assertInstanceOf(WebhookBuilder::class, $builder);
    }
}
