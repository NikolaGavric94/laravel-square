<?php

namespace Nikolag\Square\Builders\SquareRequestBuilders;

use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Utils\Constants;
use Square\Models\Fulfillment as SquareFulfillment;
use Square\Models\FulfillmentType;
use Square\Models\FulfillmentDeliveryDetails;
use Square\Models\FulfillmentPickupDetails;
use Square\Models\FulfillmentPickupDetailsCurbsidePickupDetails;
use Square\Models\FulfillmentRecipient;
use Square\Models\FulfillmentShipmentDetails;

class FulfillmentRequestBuilder
{
    /**
     * Adds curbside pickup details to the pickup details.
     *
     * @param FulfillmentPickupDetails $fulfillmentPickupDetails The Square fulfillment pickup details object.
     * @param PickupDetails            $pickupDetails            The pickup details model.
     * @return void
     */
    public function addCurbsidePickupDetails(
        FulfillmentPickupDetails $fulfillmentPickupDetails,
        PickupDetails $pickupDetails
    ): void {
        // Check if it's a curbside pickup
        if (! $pickupDetails->is_curbside_pickup) {
            return;
        }

        // Set the curbside pickup flag
        $fulfillmentPickupDetails->setIsCurbsidePickup($pickupDetails->is_curbside_pickup);

        // Set the curbside pickup details
        $curbsidePickupDetails = new FulfillmentPickupDetailsCurbsidePickupDetails();
        $curbsidePickupDetails->setCurbsideDetails($pickupDetails->curbside_pickup_details->curbside_details);
        $curbsidePickupDetails->setBuyerArrivedAt($pickupDetails->curbside_pickup_details->buyer_arrived_at);

        // Add the curbside pickup details to the pickup details
        $fulfillmentPickupDetails->setCurbsidePickupDetails($curbsidePickupDetails);
    }

    /**
     * Builds and returns array of \Square\Models\Fulfillment for order.
     *
     * @param Collection $fulfillments Collection of fulfillment models.
     * @return array<\Square\Models\Fulfillment>
     *
     * @throws InvalidSquareOrderException When fulfillment configuration is invalid.
     * @throws MissingPropertyException    When required fulfillment properties are missing.
     */
    public function buildFulfillments(Collection $fulfillments): array
    {
        $tempFulfillment = null;
        if ($fulfillments->isNotEmpty()) {
            foreach ($fulfillments as $fulfillment) {
                $tempFulfillment = new SquareFulfillment();

                // Set the state
                $tempFulfillment->setState($fulfillment->state);

                // Set the type
                $tempFulfillment->setType($fulfillment->type);

                // Based on the type, set the appropriate details
                if ($fulfillment->type == FulfillmentType::DELIVERY) {
                    // Build the delivery details
                    $tempDeliveryDetails = $this->buildDeliveryDetails($fulfillment);

                    // Set the delivery details
                    $tempFulfillment->setDeliveryDetails($tempDeliveryDetails);
                } elseif ($fulfillment->type == FulfillmentType::PICKUP) {
                    // Build the pickup details
                    $tempPickupDetails = $this->buildPickupDetails($fulfillment);

                    // Set the pickup details
                    $tempFulfillment->setPickupDetails($tempPickupDetails);
                } elseif ($fulfillment->type == FulfillmentType::SHIPMENT) {
                    // Build the shipment details
                    $tempShipmentDetails = $this->buildShipmentDetails($fulfillment);

                    // Set the shipment details
                    $tempFulfillment->setShipmentDetails($tempShipmentDetails);
                }

                // UNSUPPORTED: Line-item separated fulfillments
                // Currently only one fulfillment per order is supported
                // $tempFulfillment->setLineItemApplication($lineItemApplication);
                // $tempFulfillment->setLineItemApplication($lineItemApplication);
            }
        }

        // Only one fulfillment per order is supported
        return [$tempFulfillment];
    }

    /**
     * Builds the fulfillment details for delivery fulfillment types.
     *
     * @param Fulfillment $fulfillment The fulfillment model.
     * @return FulfillmentDeliveryDetails
     *
     * @throws InvalidSquareOrderException When delivery configuration is invalid.
     * @throws MissingPropertyException    When required delivery properties are missing.
     */
    public function buildDeliveryDetails(Fulfillment $fulfillment): FulfillmentDeliveryDetails
    {
        /** @var DeliveryDetails $deliveryDetails */
        $deliveryDetails = $fulfillment->fulfillmentDetails;

        $fulfillmentDeliveryDetails = new FulfillmentDeliveryDetails();

        // Set the recipient from the fulfillment
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($fulfillment->recipient->display_name);
        $recipient->setEmailAddress($fulfillment->recipient->email_address);
        $recipient->setPhoneNumber($fulfillment->recipient->phone_number);
        $recipient->setAddress($fulfillment->recipient->getSquareRequestAddress());
        $fulfillmentDeliveryDetails->setRecipient($recipient);

        // Time-based details
        $fulfillmentDeliveryDetails->setCompletedAt($deliveryDetails->completed_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setDeliverAt($deliveryDetails->deliver_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setDeliveredAt($deliveryDetails->delivered_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setInProgressAt($deliveryDetails->in_progress_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setPlacedAt($deliveryDetails->placed_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setReadyAt($deliveryDetails->ready_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setScheduleType($deliveryDetails->schedule_type);

        // Duration-based details
        $fulfillmentDeliveryDetails->setDeliveryWindowDuration($deliveryDetails->delivery_window_duration);
        $fulfillmentDeliveryDetails->setPrepTimeDuration($deliveryDetails->prep_time_duration);

        // Note
        $fulfillmentDeliveryDetails->setNote($deliveryDetails->note);

        // Delivery-type details
        $fulfillmentDeliveryDetails->setDropoffNotes($deliveryDetails->dropoff_notes);
        $fulfillmentDeliveryDetails->setExternalDeliveryId($deliveryDetails->external_delivery_id);
        $fulfillmentDeliveryDetails->setIsNoContactDelivery($deliveryDetails->is_no_contact_delivery);
        $fulfillmentDeliveryDetails->setManagedDelivery($deliveryDetails->managed_delivery);
        $fulfillmentDeliveryDetails->setSquareDeliveryId($deliveryDetails->square_delivery_id);

        // Courier details
        $fulfillmentDeliveryDetails->setCourierPickupAt(
            $deliveryDetails->courier_pickup_at?->format(Constants::DATE_FORMAT)
        );
        $fulfillmentDeliveryDetails->setCourierPickupWindowDuration($deliveryDetails->courier_pickup_window_duration);
        $fulfillmentDeliveryDetails->setCourierProviderName($deliveryDetails->courier_provider_name);
        $fulfillmentDeliveryDetails->setCourierSupportPhoneNumber($deliveryDetails->courier_support_phone_number);

        // Cancellation/rejection data
        $fulfillmentDeliveryDetails->setCanceledAt($deliveryDetails->canceled_at?->format(Constants::DATE_FORMAT));
        $fulfillmentDeliveryDetails->setCancelReason($deliveryDetails->cancel_reason);
        $fulfillmentDeliveryDetails->setRejectedAt($deliveryDetails->rejected_at?->format(Constants::DATE_FORMAT));

        return $fulfillmentDeliveryDetails;
    }

    /**
     * Builds the fulfillment details for pickup fulfillment types.
     *
     * @param Fulfillment $fulfillment The fulfillment model.
     * @return FulfillmentPickupDetails
     *
     * @throws InvalidSquareOrderException When pickup configuration is invalid.
     * @throws MissingPropertyException    When required pickup properties are missing.
     */
    public function buildPickupDetails(Fulfillment $fulfillment): FulfillmentPickupDetails
    {
        /** @var PickupDetails $pickupDetails */
        $pickupDetails = $fulfillment->fulfillmentDetails;

        // Create the square request fulfillment pick
        $fulfillmentPickupDetails = new FulfillmentPickupDetails();

        // Set the recipient from the fulfillment
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($fulfillment->recipient->display_name);
        $recipient->setEmailAddress($fulfillment->recipient->email_address);
        $recipient->setPhoneNumber($fulfillment->recipient->phone_number);
        // Address is optional for pickup orders
        if ($fulfillment->recipient->address) {
            $recipient->setAddress($fulfillment->recipient->getSquareRequestAddress());
        }
        $fulfillmentPickupDetails->setRecipient($recipient);

        // Pickup schedule type
        $fulfillmentPickupDetails->setScheduleType($pickupDetails->schedule_type);

        // Time-based details
        $fulfillmentPickupDetails->setAcceptedAt($pickupDetails->accepted_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setExpiresAt($pickupDetails->expires_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setExpiredAt($pickupDetails->expired_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setPickedUpAt($pickupDetails->picked_up_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setPickupAt($pickupDetails->pickup_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setPlacedAt($pickupDetails->placed_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setReadyAt($pickupDetails->ready_at?->format(Constants::DATE_FORMAT));
        $fulfillmentPickupDetails->setRejectedAt($pickupDetails->rejected_at?->format(Constants::DATE_FORMAT));

        // Note
        $fulfillmentPickupDetails->setNote($pickupDetails->note);

        // Duration-based details
        $fulfillmentPickupDetails->setAutoCompleteDuration($pickupDetails->auto_complete_duration);
        $fulfillmentPickupDetails->setPickupWindowDuration($pickupDetails->pickup_window_duration);
        $fulfillmentPickupDetails->setPrepTimeDuration($pickupDetails->prep_time_duration);

        // Cancellation/rejection data
        $fulfillmentPickupDetails->setCancelReason($pickupDetails->cancel_reason);
        $fulfillmentPickupDetails->setCanceledAt($pickupDetails->canceled_at?->format(Constants::DATE_FORMAT));

        // Add curbside pickup details
        $this->addCurbsidePickupDetails($fulfillmentPickupDetails, $pickupDetails);

        return $fulfillmentPickupDetails;
    }

    /**
     * Builds the fulfillment details for shipment fulfillment types.
     *
     * @param Fulfillment $fulfillment The fulfillment model.
     * @return FulfillmentShipmentDetails
     *
     * @throws InvalidSquareOrderException When shipment configuration is invalid.
     * @throws MissingPropertyException    When required shipment properties are missing.
     */
    public function buildShipmentDetails(Fulfillment $fulfillment): FulfillmentShipmentDetails
    {
        /** @var ShipmentDetails $shipmentDetails */
        $shipmentDetails = $fulfillment->fulfillmentDetails;

        $fulfillmentShipmentDetails = new FulfillmentShipmentDetails();

        // Set the recipient from the fulfillment
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($fulfillment->recipient->display_name);
        $recipient->setEmailAddress($fulfillment->recipient->email_address);
        $recipient->setPhoneNumber($fulfillment->recipient->phone_number);
        $recipient->setAddress($fulfillment->recipient->getSquareRequestAddress());
        $fulfillmentShipmentDetails->setRecipient($recipient);

        // Time-based details
        $fulfillmentShipmentDetails->setPlacedAt($shipmentDetails->placed_at?->format(Constants::DATE_FORMAT));
        $fulfillmentShipmentDetails->setInProgressAt($shipmentDetails->in_progress_at?->format(Constants::DATE_FORMAT));
        $fulfillmentShipmentDetails->setPackagedAt($shipmentDetails->packaged_at?->format(Constants::DATE_FORMAT));
        $fulfillmentShipmentDetails->setExpectedShippedAt(
            $shipmentDetails->expected_shipped_at?->format(Constants::DATE_FORMAT)
        );
        $fulfillmentShipmentDetails->setShippedAt($shipmentDetails->shipped_at?->format(Constants::DATE_FORMAT));

        // Carrier/shipment-type/tracking details
        $fulfillmentShipmentDetails->setCarrier($shipmentDetails->carrier);
        $fulfillmentShipmentDetails->setShippingNote($shipmentDetails->shipping_note);
        $fulfillmentShipmentDetails->setShippingType($shipmentDetails->shipping_type);
        $fulfillmentShipmentDetails->setTrackingNumber($shipmentDetails->tracking_number);
        $fulfillmentShipmentDetails->setTrackingUrl($shipmentDetails->tracking_url);

        // Cancellation/failure data
        $fulfillmentShipmentDetails->setCanceledAt($shipmentDetails->canceled_at?->format(Constants::DATE_FORMAT));
        $fulfillmentShipmentDetails->setCancelReason($shipmentDetails->cancel_reason);
        $fulfillmentShipmentDetails->setFailedAt($shipmentDetails->failed_at?->format(Constants::DATE_FORMAT));
        $fulfillmentShipmentDetails->setFailureReason($shipmentDetails->failure_reason);

        return $fulfillmentShipmentDetails;
    }
}
