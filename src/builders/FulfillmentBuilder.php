<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Utils\Constants;
use stdClass;

class FulfillmentBuilder
{
    /**
     * @var RecipientBuilder
     */
    private RecipientBuilder $recipientBuilder;

    /** @var string */
    private string $deliveryDetailsKey = 'delivery_details';

    /** @var string */
    private string $pickupDetailsKey = 'pickup_details';

    /** @var string */
    private string $shipmentDetailsKey = 'shipment_details';

    public function __construct()
    {
        $this->recipientBuilder = new RecipientBuilder();
    }

    /**
     * Checks if the fulfillment details are already set.
     *
     * @param  mixed  $fulfillmentModel
     * @return void
     *
     * @throws InvalidSquareOrderException
     */
    private function checkFulfillmentDetails(mixed $fulfillmentModel): void
    {
        // If this fulfillment already has details, throw an error - only one fulfillment details per fulfillment
        // is currently supported
        if (! empty($fulfillmentModel->fulfillmentDetails)) {
            throw new InvalidSquareOrderException('Fulfillment already has details', 500);
        }
    }

    /**
     * Add a fulfillment to the order from model as source.
     *
     * @param  Model  $fulfillment
     * @param  Model  $order
     * @return Fulfillment|stdClass
     *
     * @throws InvalidSquareOrderException
     */
    public function createFulfillmentFromModel(Model $fulfillment, Model $order): Fulfillment|stdClass
    {
        $fulfillmentObj = new stdClass();

        // Check if the order is present and if it already has this fulfillment
        // or if fulfillment doesn't have property $id then create new Fulfillment object
        if (($order && ! $order->hasFulfillment($fulfillment)) && ! Arr::has($fulfillment->toArray(), 'id')) {
            $fulfillmentObj = new Fulfillment($fulfillment->toArray());
        } else {
            $fulfillmentObj = Fulfillment::find($fulfillment->id);
        }

        // Validate that the type matches the details
        // Due to the nature of the relationship between Fulfillment and FulfillmentDetails, the fulfillment details
        // should already be a model associated with the fulfillment at this point
        if (
            $fulfillment->type == Constants::FULFILLMENT_TYPE_DELIVERY
            && ! $fulfillment->fulfillmentDetails instanceof DeliveryDetails
        ) {
            throw new InvalidSquareOrderException('Fulfillment type does not match details', 500);
        } elseif (
            $fulfillment->type == Constants::FULFILLMENT_TYPE_PICKUP
            && ! $fulfillment->fulfillmentDetails instanceof PickupDetails
        ) {
            throw new InvalidSquareOrderException('Fulfillment type does not match details', 500);
        } elseif (
            $fulfillment->type == Constants::FULFILLMENT_TYPE_SHIPMENT
            && ! $fulfillment->fulfillmentDetails instanceof ShipmentDetails
        ) {
            throw new InvalidSquareOrderException('Fulfillment type does not match details', 500);
        }

        $fulfillmentObj->fulfillmentDetails = $fulfillment->fulfillmentDetails;

        return $fulfillmentObj;
    }

    /**
     * Add a fulfillment to the order from array as source.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return Fulfillment|stdClass
     *
     * @throws MissingPropertyException
     * @throws InvalidSquareOrderException
     */
    public function createFulfillmentFromArray(
        array $fulfillment,
        Model $order = null
    ): Model|stdClass {
        $fulfillmentObj = new stdClass();

        // If fulfillment doesn't have a type in the array throw new exception - every fulfillment should have a type.
        if (! Arr::has($fulfillment, 'type') || $fulfillment['type'] == null) {
            throw new MissingPropertyException('"type" property for object Fulfillment is missing', 500);
        }

        // Check if the order is present and if it already has this fulfillment
        // or if fulfillment doesn't have property $id then create new Fulfillment object
        if (($order && ! $order->hasFulfillment($fulfillment)) || ! Arr::has($fulfillment, 'id')) {
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

        // Check for recipient
        $fulfillmentDetailsCopy->recipient = $this->getRecipientFromFulfillmentArray($fulfillment, $type);

        // Add the fulfillment details to the fulfillment object
        $tempFulfillment->fulfillmentDetails = $fulfillmentDetailsCopy;

        return $tempFulfillment;
    }

    /**
     * Create delivery details from array.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return DeliveryDetails
     *
     * @throws MissingPropertyException
     */
    public function createDeliveryDetailsFromArray(array $fulfillment, mixed $fulfillmentModel): DeliveryDetails
    {
        // Make sure the fulfillment details are not already set
        $this->checkFulfillmentDetails($fulfillmentModel);

        // Get the details
        $deliveryData = Arr::get($fulfillment, $this->deliveryDetailsKey);
        if (! $deliveryData) {
            throw new MissingPropertyException(
                $this->deliveryDetailsKey.' property for object Fulfillment is missing',
                500
            );
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
        // Make sure the fulfillment details are not already set
        $this->checkFulfillmentDetails($fulfillmentModel);

        // Get the details
        $pickupData = Arr::get($fulfillment, $this->pickupDetailsKey);
        if (! $pickupData) {
            throw new MissingPropertyException(
                $this->pickupDetailsKey.' property for object Fulfillment is missing',
                500
            );
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
        // Make sure the fulfillment details are not already set
        $this->checkFulfillmentDetails($fulfillmentModel);

        // Get the details
        $shipmentData = Arr::get($fulfillment, $this->shipmentDetailsKey);
        if (! $shipmentData) {
            throw new MissingPropertyException(
                $this->shipmentDetailsKey.' property for object Fulfillment is missing',
                500
            );
        }

        return new ShipmentDetails($shipmentData);
    }

    /**
     * Get recipient data from fulfillment.
     *
     * @param  array  $fulfillment  The fulfillment data.
     * @param  string  $type  The type of the fulfillment.
     * @return Recipient|null
     */
    private function getRecipientFromFulfillmentArray(array $fulfillment, string $type): Recipient|null
    {
        if ($type == Constants::FULFILLMENT_TYPE_DELIVERY) {
            $fulfillmentDetails = Arr::get($fulfillment, $this->deliveryDetailsKey);
        } elseif ($type == Constants::FULFILLMENT_TYPE_PICKUP) {
            $fulfillmentDetails = Arr::get($fulfillment, $this->pickupDetailsKey);
        } elseif ($type == Constants::FULFILLMENT_TYPE_SHIPMENT) {
            $fulfillmentDetails = Arr::get($fulfillment, $this->shipmentDetailsKey);
        } else {
            throw new InvalidSquareOrderException('Invalid fulfillment type', 500);
        }

        // Return the recipient data, otherwise null
        $recipient = null;
        if (Arr::has($fulfillmentDetails, 'recipient')) {
            $recipient = $this->recipientBuilder->load($fulfillmentDetails['recipient']);
        }

        return $recipient;
    }
}
