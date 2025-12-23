<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Throwable;

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
     * Pickup Details persisting.
     *
     * @return void
     */
    public function test_pickup_details_create(): void
    {
        $fakeNote = 'Pickup for '.$this->faker->name;

        factory(PickupDetails::class)->create([
            'note' => $fakeNote,
        ]);

        $this->assertDatabaseHas('nikolag_pickup_details', [
            'note' => $fakeNote,
        ]);
    }

    /**
     * Check fulfillment with pickup and recipient.
     *
     * @return void
     */
    public function test_pickup_create_with_recipient(): void
    {
        // Create pickup details
        $pickupDetails = factory(PickupDetails::class)->create();

        // Create a fulfillment and associate with pickup details and recipient
        $fulfillment = $this->data->fulfillmentWithPickupDetails;
        $fulfillment->fulfillmentDetails()->associate($pickupDetails);

        // Associate order with the fulfillment
        $fulfillment->order()->associate($this->data->order);
        $fulfillment->save();

        $this->data->fulfillmentRecipient->fulfillment()->associate($fulfillment);
        $this->data->fulfillmentRecipient->save();

        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fresh()->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $fulfillment->recipient);
    }

    /**
     * Check pickup cannot be associated directly to the order.
     *
     * @return void
     */
    public function test_pickup_associate_with_order_missing_fulfillment(): void
    {
        $order = factory(Order::class)->create();

        // Retrieve the fulfillment with pickup details
        $pickupDetails = $this->data->fulfillmentWithPickupDetails;

        // Make sure the pickup details cannot be associated with an order without the fulfillment
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        // Fulfillment to the order
        $pickupDetails->order()->associate($order);
        $pickupDetails->save();
    }
}
