<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Tests\TestCase;

class PickupDetailsTest extends TestCase
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
     * Pickup Details creation.
     *
     * @return void
     */
    public function test_pickup_details_make(): void
    {
        $pickupDetails = factory(PickupDetails::class)->create();

        $this->assertNotNull($pickupDetails, 'Pickup Details is null.');
    }

    /**
     * Pickup Details persisting
     *
     * @return void
     */
    public function test_pickup_details_create(): void
    {
        $fakeNote = 'Delivery for ' . $this->faker->name;

        factory(PickupDetails::class)->create([
            'note' => $fakeNote,
        ]);

        $this->assertDatabaseHas('nikolag_pickup_details', [
            'note' => $fakeNote,
        ]);
    }

    /**
     * Check fulfillment with pickup and recipient
     *
     * @return void
     */
    public function test_pickup_create_with_recipient(): void
    {
        // Retrieve the fulfillment with pickup details
        $fulfillment = $this->data->fulfillmentWithPickupDetails;

        // Add the recipient to the fulfillment details
        $fulfillment->fulfillmentDetails->recipient()->associate($this->data->fulfillmentRecipient);
        $fulfillment->fulfillmentDetails->save();

        // Create the fulfillment details and associate it with the fulfillment
        $fulfillment->fulfillmentDetails->save();
        $fulfillment->fulfillmentDetails()->associate($fulfillment->fulfillmentDetails);

        // Associate order with the fulfillment
        $fulfillment->order()->associate($this->data->order);
        $fulfillment->save();

        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }
}
