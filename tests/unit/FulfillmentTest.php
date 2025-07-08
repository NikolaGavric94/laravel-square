<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Square\Models\FulfillmentType;
use Throwable;

class FulfillmentTest extends TestCase
{
    private TestDataHolder $data;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::make();
    }

    /**
     * Make sure fulfillments cannot be created without fulfillment details.
     *
     * @return void
     */
    public function test_fulfillment_make(): void
    {
        // Expect an exception where fulfillment_details_id cannot be NULL
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        factory(Fulfillment::class)->create();
    }

    /**
     * Creates an order using the square facade and checks if the fulfillment is created.
     *
     * @return void
     */
    public function test_fulfillment_order_relationship(): void
    {
        // Create an order with a pickup fulfillment
        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->setFulfillment($this->data->fulfillmentWithPickupDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        // Get the order id
        $orderId = $square->getOrder()->id;

        // Query the fulfillments table
        $fulfillment = Fulfillment::where('order_id', $orderId)->first();

        // Make sure the fulfillment is created
        $this->assertNotNull($fulfillment);

        // Make sure the fulfillment is associated with the order and has the correct details
        $this->assertInstanceOf(Order::class, $fulfillment->order);
        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fulfillmentDetails);

        // Re-query the order and make sure the fulfillment is associated with it
        $order = Order::find($orderId);
        $this->assertInstanceOf(Fulfillment::class, $order->fulfillments->first());
    }

    /**
     * Check fulfillment with pickup.
     *
     * @return void
     */
    public function test_fulfillment_create_with_pickup(): void
    {
        // Create the pickup
        $pickup = factory(PickupDetails::class)->create();

        // Create the fulfillment - associate the pickup and recipient before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        $this->assertInstanceOf(PickupDetails::class, $fulfillment->fresh()->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $fulfillment->fresh()->recipient);
    }

    /**
     * Check fulfillment with delivery.
     *
     * @return void
     */
    public function test_fulfillment_create_with_delivery(): void
    {
        // Create the delivery
        $delivery = factory(DeliveryDetails::class)->create();

        // Create the fulfillment - associate the delivery and recipient before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        $this->assertInstanceOf(DeliveryDetails::class, $fulfillment->fresh()->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $fulfillment->fresh()->recipient);
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

        // Create the fulfillment - associate the shipment and recipient before saving!
        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::SHIPMENT)->make();
        $fulfillment->fulfillmentDetails()->associate($shipment);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        $this->assertInstanceOf(ShipmentDetails::class, $fulfillment->fresh()->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $fulfillment->fresh()->recipient);
    }

    /**
     * Check fulfillment persisting with orders.
     *
     * @return void
     */
    public function test_fulfillment_create_with_orders(): void
    {
        // Create a fulfillment with pickup details
        $pickup = factory(PickupDetails::class)->create();

        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Save the order
        $order->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        $this->assertInstanceOf(Order::class, $fulfillment->order);
        $this->assertInstanceOf(Recipient::class, $fulfillment->fresh()->recipient);
    }

    /**
     * Check fulfillment persisting with orders.
     *
     * @return void
     */
    public function test_fulfillment_create_without_details(): void
    {
        // Expect an exception where fulfillment_details_id cannot be NULL
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->create();
    }

    /**
     * Pickup creation without order, testing exception case.
     *
     * @return void
     */
    public function test_square_order_fulfillment_with_no_order(): void
    {
        $fulfillmentDetails = [
            'delivery_details' => $this->data->fulfillmentWithDeliveryDetails,
            'pickup_details' => $this->data->fulfillmentWithPickupDetails,
            'shipment_details' => $this->data->fulfillmentWithShipmentDetails,
        ];
        foreach ($fulfillmentDetails as $fulfillmentType => $fulfillment) {
            // Retrieve the fulfillment with Shipment Details
            $this->expectException(InvalidSquareOrderException::class);
            $this->expectExceptionMessage('Fulfillment cannot be set without an order');
            $this->expectExceptionCode(500);

            Square::setFulfillment($fulfillment);
        }
    }

    /**
     * Pickup creation without order, testing exception case.
     *
     * @return void
     */
    public function test_square_order_fulfillment_with_multiple_fulfillments(): void
    {
        $this->expectException(InvalidSquareOrderException::class);
        $this->expectExceptionMessage('This order already has a fulfillment');
        $this->expectExceptionCode(500);

        $product = factory(Product::class)->create();
        Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product, 2)
            ->setFulfillment($this->data->fulfillmentWithPickupDetails)
            ->setFulfillment($this->data->fulfillmentWithPickupDetails);
    }

    /**
     * Test one-to-one recipient constraint.
     *
     * @return void
     */
    public function test_recipient_one_to_one_constraint(): void
    {
        // Create first fulfillment with pickup details
        $pickup1 = factory(PickupDetails::class)->create();
        $fulfillment1 = factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make();
        $fulfillment1->fulfillmentDetails()->associate($pickup1);

        $order1 = factory(Order::class)->create();
        $fulfillment1->order()->associate($order1);
        $fulfillment1->save();

        // Create a recipient associated with the fulfillment
        $recipient = factory(Recipient::class)->create(['fulfillment_id' => $fulfillment1->id]);

        // Create second fulfillment with delivery details
        $delivery2 = factory(DeliveryDetails::class)->create();
        $fulfillment2 = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make();
        $fulfillment2->fulfillmentDetails()->associate($delivery2);

        $order2 = factory(Order::class)->create();
        $fulfillment2->order()->associate($order2);
        $fulfillment2->save();

        // This should fail due to unique constraint on fulfillment_id when trying to create a second recipient for the same fulfillment
        $this->expectException(Throwable::class);
        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        // Try to create a second recipient for the first fulfillment (should fail due to unique constraint)
        $recipient2 = factory(Recipient::class)->create(['fulfillment_id' => $fulfillment1->id]);
    }

    /**
     * Test recipient relationships work correctly.
     *
     * @return void
     */
    public function test_recipient_fulfillment_relationships(): void
    {
        // Create fulfillment with pickup details
        $pickup = factory(PickupDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);

        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        // Test relationships
        $this->assertInstanceOf(Recipient::class, $fulfillment->recipient);
        $this->assertInstanceOf(Fulfillment::class, $recipient->fulfillment);
        $this->assertEquals($fulfillment->id, $recipient->fulfillment->id);
        $this->assertEquals($recipient->id, $fulfillment->recipient->id);
    }
}
