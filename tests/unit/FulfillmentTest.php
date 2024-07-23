<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
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
        $fulfillment = factory(Fulfillment::class)->states(Constants::FULFILLMENT_TYPE_PICKUP)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);

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
        $fulfillment = factory(Fulfillment::class)->states(Constants::FULFILLMENT_TYPE_DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);

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
        $fulfillment = factory(Fulfillment::class)->states(Constants::FULFILLMENT_TYPE_SHIPMENT)->make();
        $fulfillment->fulfillmentDetails()->associate($shipment);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);

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
        // Create a fulfillment with pickup details
        $pickup = factory(PickupDetails::class)->create();

        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->states(Constants::FULFILLMENT_TYPE_DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($pickup);

        // Make an order and associate it with the fulfillment
        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        $fulfillment->order()->associate($order);

        // Save the order
        $order->save();

        $this->assertInstanceOf(Order::class, $fulfillment->order);
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

        factory(Fulfillment::class)->states(Constants::FULFILLMENT_TYPE_DELIVERY)->create();
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
            'pickup_details'   => $this->data->fulfillmentWithPickupDetails,
            'shipment_details' => $this->data->fulfillmentWithShipmentDetails,
        ];
        foreach ($fulfillmentDetails as $fulfillmentType => $fulfillment) {
            // Retrieve the fulfillment with Shipment Details
            $this->expectException(InvalidSquareOrderException::class);
            $this->expectExceptionMessage('Fulfillment cannot be set without an order.');
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
}
