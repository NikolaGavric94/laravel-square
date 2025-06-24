<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareSignatureException;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\WebhookVerifier;

class WebhookVerifierTest extends TestCase
{
    private string $testSignatureKey = 'test-signature-key-123';
    private string $testNotificationUrl = 'https://example.com/webhook';
    private string $validPayload;
    private array $validEventData;

    public function setUp(): void
    {
        parent::setUp();

        $this->validPayload = json_encode([
            'merchant_id' => 'test-merchant-123',
            'type' => 'order.created',
            'event_id' => 'event-123',
            'created_at' => '2024-01-01T12:00:00Z',
            'data' => [
                'type' => 'order',
                'id' => 'order-data-123',
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789'
                    ]
                ]
            ]
        ]);

        $this->validEventData = [
            'merchant_id' => 'test-merchant-123',
            'type' => 'order.created',
            'event_id' => 'event-123',
            'created_at' => '2024-01-01T12:00:00Z',
            'data' => [
                'type' => 'order',
                'id' => 'order-data-123',
                'object' => [
                    'order' => [
                        'id' => 'order-456',
                        'location_id' => 'location-789'
                    ]
                ]
            ]
        ];
    }
}
