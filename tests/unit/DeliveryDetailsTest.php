<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Throwable;

class DeliveryDetailsTest extends TestCase
{
    /**
     * Check fulfillment with delivery and recipient
     *
     * @return void
     */
    public function test_delivery_create_with_recipient(): void
    {
        $recipient = factory(Recipient::class)->create();
        // Create the delivery
        $delivery = factory(DeliveryDetails::class)->create();

        // Create the fulfillment - associate the delivery before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);
        $fulfillment->save();

        $this->assertInstanceOf(DeliveryDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }
}
