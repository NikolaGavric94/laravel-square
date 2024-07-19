<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Tests\TestCase;

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
     * Shipment Details persisting
     *
     * @return void
     */
    public function test_shipment_details_create(): void
    {
        $fakeNote = 'Delivery for ' . $this->faker->name;

        factory(ShipmentDetails::class)->create([
            'note' => $fakeNote,
        ]);

        $this->assertDatabaseHas('nikolag_shipment_details', [
            'note' => $fakeNote,
        ]);
    }

    /**
     * Check fulfillment with shipment and recipient
     *
     * @return void
     */
    public function test_shipment_create_with_recipient(): void
    {
        // Retrieve the fulfillment with shipment details
        $fulfillment = $this->data->fulfillmentWithShipmentDetails;

        // Add the recipient to the fulfillment details
        $fulfillment->fulfillmentDetails->recipient()->associate($this->data->fulfillmentRecipient);
        $fulfillment->fulfillmentDetails->save();

        // Create the fulfillment details and associate it with the fulfillment
        $fulfillment->fulfillmentDetails->save();
        $fulfillment->fulfillmentDetails()->associate($fulfillment->fulfillmentDetails);

        // Associate order with the fulfillment
        $fulfillment->order()->associate($this->data->order);
        $fulfillment->save();

        $this->assertInstanceOf(ShipmentDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }
}
