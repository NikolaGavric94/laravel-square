<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Throwable;

class ShipmentDetailsTest extends TestCase
{
    private TestDataHolder $data;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::create();
    }

    /**
     * Shipment Details creation.
     *
     * @return void
     */
    public function test_shipment_details_make(): void
    {
        $shipmentDetails = factory(ShipmentDetails::class)->create();

        $this->assertNotNull($shipmentDetails, 'Shipment Details is null.');
    }

    /**
     * Shipment Details persisting.
     *
     * @return void
     */
    public function test_shipment_details_create(): void
    {
        $fakeNote = 'Shipment for '.$this->faker->name;

        factory(ShipmentDetails::class)->create([
            'shipping_note' => $fakeNote,
        ]);

        $this->assertDatabaseHas('nikolag_shipment_details', [
            'shipping_note' => $fakeNote,
        ]);
    }

    /**
     * Check fulfillment with shipment and recipient.
     *
     * @return void
     */
    public function test_shipment_create_with_recipient(): void
    {
        // Create shipment details
        $shipmentDetails = factory(ShipmentDetails::class)->create();

        // Create a fulfillment and associate with shipment details and recipient
        $fulfillment = $this->data->fulfillmentWithShipmentDetails;
        $fulfillment->fulfillmentDetails()->associate($shipmentDetails);

        // Associate order with the fulfillment
        $fulfillment->order()->associate($this->data->order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $this->data->fulfillmentRecipient->fulfillment()->associate($fulfillment);
        $this->data->fulfillmentRecipient->save();

        $this->assertInstanceOf(ShipmentDetails::class, $fulfillment->fresh()->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $fulfillment->recipient);
    }

    /**
     * Check shipment cannot be associated directly to the order.
     *
     * @return void
     */
    public function test_shipment_associate_with_order_missing_fulfillment(): void
    {
        $order = factory(Order::class)->create();

        // Retrieve the fulfillment with shipment details
        $shipmentDetails = $this->data->fulfillmentWithShipmentDetails;

        // Make sure the shipment details cannot be associated with an order without the fulfillment
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        // Fulfillment to the order
        $shipmentDetails->order()->associate($order);
        $shipmentDetails->save();
    }
}
