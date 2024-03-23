<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use Throwable;

class FulfillmentTest extends TestCase
{
    /**
     * Make sure fulfillments cannot be created without fulfillment details.
     *
     * @return void
     */
    public function test_fulfillment_make(): void
    {
        try {
            factory(Fulfillment::class)->create();
        } catch (Throwable $e) {
            $integrityConstraintString = 'Integrity constraint violation';
            $this->assertStringContainsString($integrityConstraintString, $e->getMessage());
        }
    }

    /**
     * Check fulfillment with pickup
     *
     * @return void
     */
    public function test_fulfillment_create_with_pickup(): void
    {
        // Create the pickup
        $pickup = factory(PickupDetails::class)->create();

        // Create the fulfillment - associate the pickup before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);
        $fulfillment->save();

        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }

    /**
     * Check fulfillment with delivery
     *
     * @return void
     */
    public function test_fulfillment_create_with_delivery(): void
    {
        // Create the delivery
        $delivery = factory(DeliveryDetails::class)->create();

        // Create the fulfillment - associate the delivery before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);
        $fulfillment->save();

        $this->assertInstanceOf(DeliveryDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }

    /**
     * Check fulfillment with shipment.
     *
     * @return void
     */
    public function test_fulfillment_create_with_shipment(): void
    {
        // Create the shipment
        $shipment = factory(ShipmentDetails::class)->create();

        // Create the fulfillment - associate the shipment before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($shipment);
        $fulfillment->save();

        $this->assertInstanceOf(ShipmentDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }

    /**
     * Check fulfillment persisting with orders.
     *
     * @return void
     */
    public function test_fulfillment_create_with_orders(): void
    {
        $order = factory(Order::class)->create();

        // Create a fulfillment with pickup details
        $pickup = factory(PickupDetails::class)->create();
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);
        $fulfillment->save();

        $fulfillment->order()->associate($order);

        $this->assertInstanceOf(Order::class, $fulfillment->order);
    }
}
