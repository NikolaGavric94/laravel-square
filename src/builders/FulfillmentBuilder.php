<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Models\OrderFulfillmentPivot;
use Nikolag\Square\Utils\Constants;
use stdClass;

class FulfillmentBuilder
{
    /**
     * @var DiscountBuilder
     */
    private DiscountBuilder $discountBuilder;
    /**
     * @var TaxesBuilder
     */
    private TaxesBuilder $taxesBuilder;

    public function __construct()
    {
        $this->discountBuilder = new DiscountBuilder();
        $this->taxesBuilder    = new TaxesBuilder();
    }

    /**
     * Add a fulfillment to the order from array as source.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return Fulfillment|stdClass
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function createFulfillmentFromArray(
        array $fulfillment,
        Model $order = null
    ): Model|stdClass {
        $fulfillmentObj = new stdClass();

        // If fulfillment doesn't have a type in the array
        // throw new exception because every fulfillment should have a type
        if (! Arr::has($fulfillment, 'type') || $fulfillment['type'] == null) {
            throw new MissingPropertyException('"type" property for object Fulfillment is missing', 500);
        }

        // Check if order is present and if already has this fulfillment
        // or if fulfillment doesn't have property $id then create new Fulfillment object
        if (($order && !$order->hasFulfillment($fulfillment)) || ! Arr::has($fulfillment, 'id')) {
            $tempFulfillment = new Fulfillment($fulfillment);
        } else {
            $tempFulfillment = Fulfillment::find($fulfillment['id']);
        }

        // Determine which type of fulfillment details we need to create
        $type = $fulfillment['type'];
        if ($type == Constants::FULFILLMENT_TYPE_DELIVERY) {
            $fulfillmentDetailsCopy = $this->createDeliveryDetailsFromArray($fulfillment, $tempFulfillment);
        } elseif ($type == Constants::FULFILLMENT_TYPE_PICKUP) {
            $fulfillmentDetailsCopy = $this->createPickupDetailsFromArray($fulfillment, $tempFulfillment);
        } elseif ($type == Constants::FULFILLMENT_TYPE_SHIPMENT) {
            $fulfillmentDetailsCopy = $this->createShipmentDetailsFromArray($fulfillment, $tempFulfillment);
        } else {
            throw new InvalidSquareOrderException('Invalid fulfillment type', 500);
        }

        $fulfillmentObj = $tempFulfillment;
        $fulfillmentObj->fulfillmentDetails = $fulfillmentDetailsCopy;

        return $fulfillmentObj;
    }

    /**
     * Create pickup details from array.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return DeliveryDetails
     *
     * @throws MissingPropertyException
     */
    public function createDeliveryDetailsFromArray(array $fulfillment, mixed $fulfillmentModel): DeliveryDetails
    {
        // If this fulfillment already has details, throw an error - only one fulfillment details per fulfillment
        // is currently supported
        if ((!empty($fulfillmentModel->fulfillmentDetails))) {
            throw new InvalidSquareOrderException('Fulfillment already has details', 500);
        }

        // Get the details
        $deliveryData = Arr::get($fulfillment, 'delivery_details');
        if (!$deliveryData) {
            throw new MissingPropertyException('delivery_details property for object Fulfillment is missing', 500);
        }
        return new DeliveryDetails($deliveryData);
    }

    /**
     * Create pickup details from array.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return PickupDetails
     *
     * @throws MissingPropertyException
     */
    public function createPickupDetailsFromArray(array $fulfillment, mixed $fulfillmentModel): PickupDetails
    {
        // If this fulfillment already has details, throw an error - only one fulfillment details per fulfillment
        // is currently supported
        if ((!empty($fulfillmentModel->fulfillmentDetails))) {
            throw new InvalidSquareOrderException('Fulfillment already has details', 500);
        }

        // Get the details
        $pickupData = Arr::get($fulfillment, 'pickup_details');
        if (!$pickupData) {
            throw new MissingPropertyException('pickup_details property for object Fulfillment is missing', 500);
        }
        return new PickupDetails($pickupData);
    }

    /**
     * Create shipment details from array.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return ShipmentDetails
     *
     * @throws MissingPropertyException
     */
    public function createShipmentDetailsFromArray(array $fulfillment, mixed $fulfillmentModel): ShipmentDetails
    {
        // If this fulfillment already has details, throw an error - only one fulfillment details per fulfillment
        // is currently supported
        if ((!empty($fulfillmentModel->fulfillmentDetails))) {
            throw new InvalidSquareOrderException('Fulfillment already has details', 500);
        }

        // Get the details
        $shipmentData = Arr::get($fulfillment, 'shipment_details');
        if (!$shipmentData) {
            throw new MissingPropertyException('shipment_details property for object Fulfillment is missing', 500);
        }
        return new ShipmentDetails($shipmentData);
    }
}
