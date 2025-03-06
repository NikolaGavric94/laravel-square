<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Throwable;

class DeliveryDetailsTest extends TestCase
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
     * Delivery details creation.
     *
     * @return void
     */
    public function test_delivery_details_make(): void
    {
        $deliveryDetails = factory(DeliveryDetails::class)->create();

        $this->assertNotNull($deliveryDetails, 'Delivery Details is null.');
    }

    /**
     * Delivery details persisting.
     *
     * @return void
     */
    public function test_delivery_details_create(): void
    {
        $fakeNote = 'Delivery for '.$this->faker->name;

        factory(DeliveryDetails::class)->create([
            'note' => $fakeNote,
        ]);

        $this->assertDatabaseHas('nikolag_delivery_details', [
            'note' => $fakeNote,
        ]);
    }

    /**
     * Check fulfillment with delivery and recipient.
     *
     * @return void
     */
    public function test_delivery_create_with_recipient(): void
    {
        // Retrieve the fulfillment with delivery details
        $fulfillment = $this->data->fulfillmentWithDeliveryDetails;

        // Add the recipient to the fulfillment details
        $fulfillment->fulfillmentDetails->recipient()->associate($this->data->fulfillmentRecipient);
        $fulfillment->fulfillmentDetails->save();

        // Create the fulfillment details and associate it with the fulfillment
        $fulfillment->fulfillmentDetails->save();
        $fulfillment->fulfillmentDetails()->associate($fulfillment->fulfillmentDetails);

        // Associate order with the fulfillment
        $fulfillment->order()->associate($this->data->order);
        $fulfillment->save();

        $this->assertInstanceOf(DeliveryDetails::class, $fulfillment->fresh()->fulfillmentDetails);
    }

    /**
     * Check delivery cannot be associated directly to the order.
     *
     * @return void
     */
    public function test_delivery_associate_with_order_missing_fulfillment(): void
    {
        $order = factory(Order::class)->create();

        // Retrieve the fulfillment with delivery details
        $deliveryDetails = $this->data->fulfillmentWithDeliveryDetails;

        // Make sure the delivery details cannot be associated with an order without the fulfillment
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        // Fulfillment to the order
        $deliveryDetails->order()->associate($order);
        $deliveryDetails->save();
    }
}
