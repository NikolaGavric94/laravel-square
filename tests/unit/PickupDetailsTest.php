<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Throwable;

class PickupDetailsTest extends TestCase
{
    /**
     * Check fulfillment with pickup and recipient
     *
     * @return void
     */
    public function test_pickup_create_with_recipient(): void
    {
        $recipient = factory(Recipient::class)->create();
        // Create the pickup
        $pickup = factory(PickupDetails::class)->create();

        // Create the fulfillment - associate the pickup before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);
        $fulfillment->save();

        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }
}
