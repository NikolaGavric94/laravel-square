<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Throwable;

class ShipmentDetailsTest extends TestCase
{
    /**
     * Check fulfillment with shipment and recipient
     *
     * @return void
     */
    public function test_shipment_create_with_recipient(): void
    {
        $recipient = factory(Recipient::class)->create();
        // Create the shipment
        $shipment = factory(ShipmentDetails::class)->create();

        // Create the fulfillment - associate the shipment before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($shipment);
        $fulfillment->save();

        $this->assertInstanceOf(ShipmentDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }
}
