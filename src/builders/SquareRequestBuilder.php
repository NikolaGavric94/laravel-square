<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\CreatePaymentRequest;
use Square\Models\Fulfillment;
use Square\Models\FulfillmentPickupDetails;
use Square\Models\FulfillmentDeliveryDetails;
use Square\Models\FulfillmentShipmentDetails;
use Square\Models\Money;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\OrderLineItemAppliedDiscount;
use Square\Models\OrderLineItemAppliedTax;
use Square\Models\OrderLineItemDiscount;
use Square\Models\OrderLineItemTax;
use Square\Models\UpdateCustomerRequest;

class SquareRequestBuilder
{
    /**
     * Item line level taxes which need to be applied to order.
     *
     * @var Collection
     */
    private Collection $productTaxes;
    /**
     * Item line level taxes which need to be applied to order.
     *
     * @var Collection
     */
    private Collection $productDiscounts;

    /**
     * SquareRequestBuilder constructor.
     */
    public function __construct()
    {
        $this->productTaxes = collect([]);
        $this->productDiscounts = collect([]);
    }

    /**
     * Create and return charge request.
     *
     * @param  array  $prepData
     * @return CreatePaymentRequest
     */
    public function buildChargeRequest(array $prepData): CreatePaymentRequest
    {
        $money = new Money();
        $money->setCurrency($prepData['amount_money']['currency']);
        $money->setAmount($prepData['amount_money']['amount']);
        $request = new CreatePaymentRequest($prepData['source_id'], $prepData['idempotency_key']);
        $request->setAmountMoney($money);
        $request->setAutocomplete($prepData['autocomplete']);
        $request->setLocationId($prepData['location_id']);
        $request->setNote($prepData['note']);
        $request->setReferenceId($prepData['reference_id']);

        if (array_key_exists('verification_token', $prepData)) {
            $request->setVerificationToken($prepData['verification_token']);
        }

        return $request;
    }

    /**
     * Create and return customer request.
     *
     * @param  Model  $customer
     * @return CreateCustomerRequest|UpdateCustomerRequest
     */
    public function buildCustomerRequest(Model $customer): UpdateCustomerRequest|CreateCustomerRequest
    {
        if ($customer->payment_service_id) {
            $request = new UpdateCustomerRequest();
        } else {
            $request = new CreateCustomerRequest();
        }
        $request->setGivenName($customer->first_name);
        $request->setFamilyName($customer->last_name);
        $request->setCompanyName($customer->company_name);
        $request->setNickname($customer->nickname);
        $request->setEmailAddress($customer->email);
        $request->setPhoneNumber($customer->phone);
        $request->setReferenceId($customer->owner_id);
        $request->setNote($customer->note);

        return $request;
    }

    /**
     * Create and return order request.
     *
     * @param  Model  $order
     * @param  string  $locationId
     * @param  string  $currency
     * @return CreateOrderRequest
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildOrderRequest(Model $order, string $locationId, string $currency): CreateOrderRequest
    {
        $squareOrder = new Order($locationId);
        $squareOrder->setReferenceId($order->id);
        $squareOrder->setLineItems($this->buildProducts($order->products, $currency));
        $squareOrder->setDiscounts($this->buildDiscounts($order->discounts, $currency));
        $squareOrder->setTaxes($this->buildTaxes($order->taxes));
        $squareOrder->setFulfillments($this->buildFulfillments($order->fulfillments));
        $request = new CreateOrderRequest();
        $request->setOrder($squareOrder);
        $request->setIdempotencyKey(uniqid());

        return $request;
    }

    /**
     * Builds and returns array of discounts.
     *
     * @param  Collection  $discounts
     * @param  string  $currency
     * @return array
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildDiscounts(Collection $discounts, string $currency): array
    {
        $temp = [];
        if ($discounts->isNotEmpty()) {
            foreach ($discounts as $discount) {
                //If discount doesn't have amount OR percentage in discount table
                //throw new exception because it should have at least 1
                $amount = $discount->amount;
                $percentage = $discount->percentage;
                if (($amount == null || $amount == 0) && ($percentage == null || $percentage == 0.0)) {
                    throw new MissingPropertyException('Both $amount and $percentage property for object Discount are missing, 1 is required', 500);
                }
                //If discount have amount AND percentage in discount table
                //throw new exception because it should only 1
                if (($amount != null || $amount != 0) && ($percentage != null || $percentage != 0.0)) {
                    throw new InvalidSquareOrderException('Both $amount and $percentage exist for object Discount, only 1 is allowed', 500);
                }
                $tempDiscount = new OrderLineItemDiscount();
                $tempDiscount->setUid(Util::uid());
                $tempDiscount->setName($discount->name);
                $tempDiscount->setScope($discount->pivot->scope);

                // If it's LINE ITEM then assign proper UID
                if ($discount->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                    $found = $this->productDiscounts->first(function ($disc) use ($discount) {
                        return $disc->getName() === $discount->name;
                    });

                    if ($found) {
                        $tempDiscount->setUid($found->getUid());
                    }
                }
                //If percentage exists append it
                if ($percentage && $percentage != 0.0) {
                    $tempDiscount->setPercentage((string) $percentage);
                    $tempDiscount->setType(Constants::DEDUCTIBLE_FIXED_PERCENTAGE);
                }
                //If amount exists append it
                if ($amount && $amount != 0) {
                    $money = new Money();
                    $money->setAmount($amount);
                    $money->setCurrency($currency);
                    $tempDiscount->setAmountMoney($money);
                    $tempDiscount->setType(Constants::DEDUCTIBLE_FIXED_AMOUNT);
                }

                $temp[] = $tempDiscount;
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of \Square\Models\Fulfillment for order.
     *
     * @param  Collection  $fulfillments
     * @return array<\Square\Models\Fulfillment>
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildFulfillments(Collection $fulfillments): array
    {
        $temp = [];
        if ($fulfillments->isNotEmpty()) {
            foreach ($fulfillments as $fulfillment) {
                $tempFulfillment = new Fulfillment();

                // Set the state
                $tempFulfillment->setState($fulfillment->state);

                // Set the type
                $tempFulfillment->setType($fulfillment->type);

                // Based on the type, set the appropriate details
                if ($fulfillment->type == Constants::FULFILLMENT_TYPE_DELIVERY) {
                    // Build the delivery details
                    $tempDeliveryDetails = $this->buildDeliveryDetails($fulfillment->fulfillmentDetails);

                    // Set the delivery details
                    $tempFulfillment->setDeliveryDetails($tempDeliveryDetails);
                } elseif ($fulfillment->type == Constants::FULFILLMENT_TYPE_PICKUP) {
                    // Build the pickup details
                    $tempPickupDetails = $this->buildPickupDetails($fulfillment->fulfillmentDetails);

                    // Set the pickup details
                    $tempFulfillment->setPickupDetails($tempPickupDetails);
                } elseif ($fulfillment->type == Constants::FULFILLMENT_TYPE_SHIPMENT) {
                    // Build the shipment details
                    $tempShipmentDetails = $this->buildShipmentDetails($fulfillment->fulfillmentDetails);

                    // Set the shipment details
                    $tempFulfillment->setShipmentDetails($tempShipmentDetails);
                }

                // TODO: Add support for line-item applications
                // $tempFulfillment->setLineItemApplication($lineItemApplication);

                // TODO: Add support for specifying line-item entries
                // $tempFulfillment->setLineItemApplication($lineItemApplication);

                $temp[] = $tempFulfillment;
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied discounts.
     *
     * @param  Collection  $discounts
     * @return array
     */
    public function buildAppliedDiscounts(Collection $discounts): array
    {
        $temp = [];
        if ($discounts->isNotEmpty()) {
            foreach ($discounts as $discount) {
                $tempDiscount = new OrderLineItemAppliedDiscount($discount->getUid());
                $tempDiscount->setUid(Util::uid());
                $temp[] = $tempDiscount;
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of taxes.
     *
     * @param  Collection  $taxes
     * @return array
     *
     * @throws MissingPropertyException
     */
    public function buildTaxes(Collection $taxes): array
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                $tempTax = new OrderLineItemTax();
                //If percentage doesn't exist in tax table
                //throw new exception because it should exist
                $percentage = $tax->percentage;
                if ($percentage == null || $percentage == 0.0) {
                    throw new MissingPropertyException('$percentage property for object Tax is missing or is invalid', 500);
                }

                $tempTax->setUid(Util::uid());
                $tempTax->setName($tax->name);
                $tempTax->setType($tax->type);
                $tempTax->setPercentage((string) $percentage);
                $tempTax->setScope($tax->pivot->scope);

                // If it's LINE ITEM then assign proper UID
                if ($tax->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                    $found = $this->productTaxes->first(function ($inner) use ($tax) {
                        return $inner->getName() === $tax->name;
                    });

                    if ($found) {
                        $tempTax->setUid($found->getUid());
                    }
                }

                $temp[] = $tempTax;
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied taxes.
     *
     * @param  Collection  $taxes
     * @return array
     *
     * @throws \Exception
     */
    public function buildAppliedTaxes(Collection $taxes): array
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                $tempTax = new OrderLineItemAppliedTax($tax->getUid());
                $tempTax->setUid(Util::uid());
                $temp[] = $tempTax;
            }
        }

        return $temp;
    }

    /**
     * Builds the fulfillment details for pickup fulfillment types.
     *
     * @param  DeliveryDetails  $deliveryDetails
     * @return FulfillmentDeliveryDetails
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildDeliveryDetails(DeliveryDetails $fulfillmentDetails): FulfillmentDeliveryDetails
    {
        $deliveryDetails = new FulfillmentDeliveryDetails();

        // TODO: Add support for recipient details
        // // Set the recipient
        // $recipient = new FulfillmentRecipient();
        // $recipient->setDisplayName($fulfillmentDetails->recipient->display_name);
        // $recipient->setEmailAddress($fulfillmentDetails->recipient->email_address);
        // $recipient->setPhoneNumber($fulfillmentDetails->recipient->phone_number);
        // $deliveryDetails->setRecipient($recipient);

        $deliveryDetails->setScheduleType($fulfillmentDetails->schedule_type);
        $deliveryDetails->setPlacedAt($fulfillmentDetails->placed_at);
        $deliveryDetails->setDeliverAt($fulfillmentDetails->deliver_at);
        $deliveryDetails->setPrepTimeDuration($fulfillmentDetails->prep_time_duration);
        $deliveryDetails->setDeliveryWindowDuration($fulfillmentDetails->delivery_window_duration);
        $deliveryDetails->setNote($fulfillmentDetails->note);
        $deliveryDetails->setCompletedAt($fulfillmentDetails->completed_at);
        $deliveryDetails->setInProgressAt($fulfillmentDetails->in_progress_at);
        $deliveryDetails->setRejectedAt($fulfillmentDetails->rejected_at);
        $deliveryDetails->setReadyAt($fulfillmentDetails->ready_at);
        $deliveryDetails->setDeliveredAt($fulfillmentDetails->delivered_at);
        $deliveryDetails->setCanceledAt($fulfillmentDetails->canceled_at);
        $deliveryDetails->setCancelReason($fulfillmentDetails->cancel_reason);
        $deliveryDetails->setCourierPickupAt($fulfillmentDetails->courier_pickup_at);
        $deliveryDetails->setCourierPickupWindowDuration($fulfillmentDetails->courier_pickup_window_duration);
        $deliveryDetails->setIsNoContactDelivery($fulfillmentDetails->is_no_contact_delivery);
        $deliveryDetails->setDropoffNotes($fulfillmentDetails->dropoff_notes);
        $deliveryDetails->setCourierProviderName($fulfillmentDetails->courier_provider_name);
        $deliveryDetails->setCourierSupportPhoneNumber($fulfillmentDetails->courier_support_phone_number);
        $deliveryDetails->setSquareDeliveryId($fulfillmentDetails->square_delivery_id);
        $deliveryDetails->setExternalDeliveryId($fulfillmentDetails->external_delivery_id);
        $deliveryDetails->setManagedDelivery($fulfillmentDetails->managed_delivery);

        return $deliveryDetails;
    }

    /**
     * Builds the fulfillment details for pickup fulfillment types.
     *
     * @param  PickupDetails  $fulfillmentDetails
     * @return FulfillmentPickupDetails
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildPickupDetails(PickupDetails $fulfillmentDetails): FulfillmentPickupDetails
    {
        $pickupDetails = new FulfillmentPickupDetails();

        // TODO: Add support for recipient details
        // // Set the recipient
        // $recipient = new FulfillmentRecipient();
        // $recipient->setDisplayName($fulfillmentDetails->recipient->display_name);
        // $recipient->setEmailAddress($fulfillmentDetails->recipient->email_address);
        // $recipient->setPhoneNumber($fulfillmentDetails->recipient->phone_number);
        // $pickupDetails->setRecipient($recipient);

        $pickupDetails->setExpiresAt($fulfillmentDetails->expires_at);
        $pickupDetails->setAutoCompleteDuration($fulfillmentDetails->auto_complete_duration);
        $pickupDetails->setScheduleType($fulfillmentDetails->schedule_type);
        $pickupDetails->setPickupAt($fulfillmentDetails->pickup_at);
        $pickupDetails->setPickupWindowDuration($fulfillmentDetails->pickup_window_duration);
        $pickupDetails->setPrepTimeDuration($fulfillmentDetails->prep_time_duration);
        $pickupDetails->setNote($fulfillmentDetails->note);
        $pickupDetails->setPlacedAt($fulfillmentDetails->placed_at);
        $pickupDetails->setAcceptedAt($fulfillmentDetails->accepted_at);
        $pickupDetails->setRejectedAt($fulfillmentDetails->rejected_at);
        $pickupDetails->setReadyAt($fulfillmentDetails->ready_at);
        $pickupDetails->setExpiredAt($fulfillmentDetails->expired_at);
        $pickupDetails->setPickedUpAt($fulfillmentDetails->picked_up_at);
        $pickupDetails->setCanceledAt($fulfillmentDetails->canceled_at);
        $pickupDetails->setCancelReason($fulfillmentDetails->cancel_reason);

        // TODO: Enable curbside pickup
        // Set the curbside pickup flag
        // $pickupDetails->setIsCurbsidePickup($fulfillmentDetails->is_curbside_pickup);

        // TODO: Enable curbside pickup details
        // Set the curbside pickup details
        // $curbsidePickupDetails = new FulfillmentPickupDetailsCurbsidePickupDetails();
        // $curbsidePickupDetails->setCurbsideSpotId($fulfillmentDetails->curbside_pickup_details->curbside_spot_id);
        // $curbsidePickupDetails->setCurbsideSpotName($fulfillmentDetails->curbside_pickup_details->curbside_spot_name);
        // $curbsidePickupDetails->setCurbsideSpotDescription($fulfillmentDetails->curbside_pickup_details->curbside_spot_description);
        // $pickupDetails->setCurbsidePickupDetails($curbsidePickupDetails);

        return $pickupDetails;
    }

    /**
     * Builds and returns array of \SquareConnect\Model\OrderLineItem for order.
     *
     * @param  Collection  $products
     * @param  string  $currency
     * @return array
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildProducts(Collection $products, string $currency): array
    {
        $temp = [];
        if ($products->isNotEmpty()) {
            foreach ($products as $product) {
                // Get product from pivot model
                $pivotProduct = $product->pivot;
                //If product doesn't have quantity
                //throw new exception because every product should
                //have at least 1 quantity
                $quantity = $pivotProduct->quantity;
                if ($quantity == null || $quantity == 0) {
                    throw new MissingPropertyException('$quantity property for object Product is missing', 500);
                }

                //Build product level taxes so we can append them to order later
                $taxes = collect($this->buildTaxes($pivotProduct->taxes));
                $this->productTaxes = $this->productTaxes->merge($taxes);

                //Build product level discounts so we can append them to order later
                $discounts = collect($this->buildDiscounts($pivotProduct->discounts, $currency));
                $this->productDiscounts = $this->productDiscounts->merge($discounts);

                $money = new Money();
                $money->setAmount($product->price);
                $money->setCurrency($currency);
                $tempProduct = new OrderLineItem($quantity);
                $tempProduct->setName($product->name);
                $tempProduct->setBasePriceMoney($money);
                $tempProduct->setQuantity((string) $quantity);
                $tempProduct->setVariationName($product->variation_name);
                $tempProduct->setNote($product->note);
                $tempProduct->setAppliedDiscounts($this->buildAppliedDiscounts($discounts));
                $tempProduct->setAppliedTaxes($this->buildAppliedTaxes($taxes));
                $temp[] = $tempProduct;
            }
        }

        return $temp;
    }

    /**
     * Builds the fulfillment details for pickup fulfillment types.
     *
     * @param  DeliveryDetails  $deliveryDetails
     * @return FulfillmentShipmentDetails
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildShipmentDetails(ShipmentDetails $fulfillmentDetails): FulfillmentShipmentDetails
    {
        $shipmentDetails = new FulfillmentShipmentDetails();

        // TODO: Add support for recipient details
        // // Set the recipient
        // $recipient = new FulfillmentRecipient();
        // $recipient->setDisplayName($fulfillmentDetails->recipient->display_name);
        // $recipient->setEmailAddress($fulfillmentDetails->recipient->email_address);
        // $recipient->setPhoneNumber($fulfillmentDetails->recipient->phone_number);
        // $deliveryDetails->setRecipient($recipient);

        $shipmentDetails->setCarrier($fulfillmentDetails->carrier);
        $shipmentDetails->setShippingNote($fulfillmentDetails->shipping_note);
        $shipmentDetails->setShippingType($fulfillmentDetails->shipping_type);
        $shipmentDetails->setTrackingNumber($fulfillmentDetails->tracking_number);
        $shipmentDetails->setTrackingUrl($fulfillmentDetails->tracking_url);
        $shipmentDetails->setPlacedAt($fulfillmentDetails->placed_at);
        $shipmentDetails->setInProgressAt($fulfillmentDetails->in_progress_at);
        $shipmentDetails->setPackagedAt($fulfillmentDetails->packaged_at);
        $shipmentDetails->setExpectedShippedAt($fulfillmentDetails->expected_shipped_at);
        $shipmentDetails->setShippedAt($fulfillmentDetails->shipped_at);
        $shipmentDetails->setCanceledAt($fulfillmentDetails->canceled_at);
        $shipmentDetails->setCancelReason($fulfillmentDetails->cancel_reason);
        $shipmentDetails->setFailedAt($fulfillmentDetails->failed_at);
        $shipmentDetails->setFailureReason($fulfillmentDetails->failure_reason);

        return $shipmentDetails;
    }
}
