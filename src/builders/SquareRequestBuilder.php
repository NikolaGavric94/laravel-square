<?php

namespace Nikolag\Square\Builders;

use Str;
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
use Square\Models\CatalogPricingType;
use Square\Models\CreatePaymentRequest;
use Square\Models\CatalogObject;
use Square\Models\CatalogObjectType;
use Square\Models\CreateCatalogImageRequest;
use Square\Models\Fulfillment;
use Square\Models\FulfillmentPickupDetails;
use Square\Models\FulfillmentPickupDetailsCurbsidePickupDetails;
use Square\Models\FulfillmentDeliveryDetails;
use Square\Models\FulfillmentRecipient;
use Square\Models\FulfillmentShipmentDetails;
use Square\Models\Money;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\OrderLineItemAppliedDiscount;
use Square\Models\OrderLineItemAppliedTax;
use Square\Models\OrderLineItemDiscount;
use Square\Models\OrderLineItemTax;
use Square\Models\TaxCalculationPhase;
use Square\Models\TaxInclusionType;
use Square\Models\UpdateCustomerRequest;
use Square\Models\Builders\CatalogCategoryBuilder;
use Square\Models\Builders\CatalogImageBuilder;
use Square\Models\Builders\CatalogItemBuilder;
use Square\Models\Builders\CatalogItemVariationBuilder;
use Square\Models\Builders\CatalogObjectBuilder;
use Square\Models\Builders\CatalogTaxBuilder;
use Square\Models\Builders\CreateCatalogImageRequestBuilder;
use Square\Models\Builders\MoneyBuilder;

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
     * Builds a category catalog object item.
     *
     * @param string  $id           The ID of the category.
     * @param string  $name         The name of the category.
     * @param boolean $allLocations Whether the category is present at all locations.
     *
     * @return CatalogObject
     */
    public function buildCategoryCatalogObject(
        string $id,
        string $name,
        bool $allLocations = true
    ): CatalogObject {
        return CatalogObjectBuilder::init(
            CatalogObjectType::CATEGORY,
            $id
        )
            ->presentAtAllLocations($allLocations)
            ->categoryData(
                CatalogCategoryBuilder::init()
                    ->name($name)
                    ->build()
            )
            ->build();
    }

    /**
     * Builds an item catalog object item.
     *
     * @param string               $name         The name of the item.
     * @param array                $taxIDs       The tax IDs of the item.
     * @param string               $description  The description of the item.
     * @param array<CatalogObject> $variations   The variations of the item.
     * @param string               $categoryID   The category of the item.
     * @param boolean              $allLocations Whether the item is present at all locations.
     *
     * @return CatalogObject
     */
    public function buildItemCatalogObject(
        string $name,
        array $taxIDs,
        string $description,
        array $variations,
        string $categoryID,
        bool $allLocations = true
    ): CatalogObject {
        // Create a catalog item builder
        $catalogItemBuilder = CatalogItemBuilder::init()
            ->name($name)
            ->taxIds($taxIDs)
            ->variations($variations)
            ->categoryId($categoryID);

        // Add the description to the catalog item builder
        if (!empty($description)) {
            if ($description != strip_tags($description)) {
                $catalogItemBuilder->descriptionHtml($description);
            } else {
                $catalogItemBuilder->description($description);
            }
        }

        return CatalogObjectBuilder::init(
            CatalogObjectType::ITEM,
            '#' . $name
        )
            ->presentAtAllLocations($allLocations)
            ->itemData($catalogItemBuilder->build())
            ->build();
    }

    /**
     * Builds a tax catalog object item.
     *
     * @param string  $name                   The name of the tax.
     * @param string  $percentage             The percentage of the tax.
     * @param string  $calculationPhase       The calculation phase of the tax.
     * @param string  $inclusionType          The inclusion type of the tax.
     * @param boolean $appliesToCustomAmounts Whether the tax applies to custom amounts.
     * @param boolean $enabled                Whether the tax is enabled.
     * @param boolean $allLocations           Whether the tax is present at all locations.
     *
     * @return CatalogObject
     */
    public function buildTaxCatalogObject(
        string $name,
        string $percentage,
        string $calculationPhase = TaxCalculationPhase::TAX_TOTAL_PHASE,
        string $inclusionType = TaxInclusionType::ADDITIVE,
        bool $appliesToCustomAmounts = true,
        bool $enabled = true,
        bool $allLocations = true
    ): CatalogObject {
        return CatalogObjectBuilder::init(
            CatalogObjectType::TAX,
            '#' . $name
        )
            ->presentAtAllLocations($allLocations)
            ->taxData(
                CatalogTaxBuilder::init()
                    ->name($name)
                    ->calculationPhase($calculationPhase)
                    ->inclusionType($inclusionType)
                    ->percentage($percentage)
                    ->appliesToCustomAmounts($appliesToCustomAmounts)
                    ->enabled($enabled)
                    ->build()
            )
            ->build();
    }

    /**
     * Builds a money object.
     *
     * @param integer $amount   The amount of the money.
     * @param string  $currency The currency of the money.
     *
     * @return Money
     */
    public function buildMoney(
        int $amount,
        string $currency
    ): Money {
        return MoneyBuilder::init()
            ->amount($amount)
            ->currency($currency)
            ->build();
    }

    /**
     * Builds a variation catalog object item.
     *
     * @param string  $name         The name of the variation.
     * @param string  $variationID  The variation ID of the variation.
     * @param string  $itemID       The item ID of the item for which the variation is being built.
     * @param Money   $priceMoney   The price money of the variation.
     * @param string  $pricingType  The pricing type of the variation.
     * @param boolean $allLocations Whether the variation is present at all locations.
     *
     * @return CatalogObject
     */
    public function buildVariationCatalogObject(
        string $name,
        string $variationID,
        string $itemID,
        Money $priceMoney,
        string $pricingType = CatalogPricingType::FIXED_PRICING,
        bool $allLocations = true
    ): CatalogObject {
        return CatalogObjectBuilder::init(
            CatalogObjectType::ITEM_VARIATION,
            $variationID
        )
            ->presentAtAllLocations($allLocations)
            ->itemVariationData(
                CatalogItemVariationBuilder::init()
                    ->itemId($itemID)
                    ->name($name)
                    ->pricingType($pricingType)
                    ->priceMoney($priceMoney)
                    ->build()
            )
            ->build();
    }

    /**
     * Uploads an image file to be represented by a CatalogImage object that can be linked to an existing CatalogObject
     * instance. The resulting CatalogImage is unattached to any CatalogObject if the object_id is not specified.
     *
     * This CreateCatalogImage endpoint accepts HTTP multipart/form-data requests with a JSON part and an image file
     * part in JPEG, PJPEG, PNG, or GIF format. The maximum file size is 15MB.
     *
     * @param string $catalogObjectId The ID of the object to which the image is attached.
     * @param string $caption         The caption of the image.
     * @param bool   $isPrimary       Whether the image is the primary image.
     *
     * @return CreateCatalogImageRequest
     */
    public function buildCatalogImageRequest(
        string $catalogObjectId,
        string $caption = '',
        bool $isPrimary = true
    ): CreateCatalogImageRequest {
        $builder =  CreateCatalogImageRequestBuilder::init(
            (string) Str::uuid(), // Generate an idempotencyKey
            CatalogObjectBuilder::init(
                CatalogObjectType::IMAGE,
                '#TEMP_ID'
            )
                ->imageData(
                    CatalogImageBuilder::init()
                        ->caption($caption)
                        ->build()
                )
                ->build()
        )
        ->isPrimary($isPrimary)
        ->objectId($catalogObjectId);

        return $builder->build();
    }

    /**
     * Adds curb side pickup details to the pickup details.
     *
     * @param  PickupDetails  $fulfillmentDetails
     *
     * @return void
     */
    public function addCurbsidePickupDetails(
        FulfillmentPickupDetails $fulfillmentPickupDetails,
        PickupDetails $pickupDetails
    ): void {
        // Check if it's a curbside pickup
        if (!$pickupDetails->is_curbside_pickup) {
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

                // UNSUPPORTED: Line-item separated fulfillments
                // Currently only one fulfillment per order is supported
                // $tempFulfillment->setLineItemApplication($lineItemApplication);
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
    public function buildDeliveryDetails(DeliveryDetails $deliveryDetails): FulfillmentDeliveryDetails
    {
        $fulfillmentDeliveryDetails = new FulfillmentDeliveryDetails();

        // Set the recipient
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($deliveryDetails->recipient->display_name);
        $recipient->setEmailAddress($deliveryDetails->recipient->email_address);
        $recipient->setPhoneNumber($deliveryDetails->recipient->phone_number);
        $fulfillmentDeliveryDetails->setRecipient($recipient);

        $fulfillmentDeliveryDetails->setScheduleType($deliveryDetails->schedule_type);
        $fulfillmentDeliveryDetails->setPlacedAt($deliveryDetails->placed_at);
        $fulfillmentDeliveryDetails->setDeliverAt($deliveryDetails->deliver_at);
        $fulfillmentDeliveryDetails->setPrepTimeDuration($deliveryDetails->prep_time_duration);
        $fulfillmentDeliveryDetails->setDeliveryWindowDuration($deliveryDetails->delivery_window_duration);
        $fulfillmentDeliveryDetails->setNote($deliveryDetails->note);
        $fulfillmentDeliveryDetails->setCompletedAt($deliveryDetails->completed_at);
        $fulfillmentDeliveryDetails->setInProgressAt($deliveryDetails->in_progress_at);
        $fulfillmentDeliveryDetails->setRejectedAt($deliveryDetails->rejected_at);
        $fulfillmentDeliveryDetails->setReadyAt($deliveryDetails->ready_at);
        $fulfillmentDeliveryDetails->setDeliveredAt($deliveryDetails->delivered_at);
        $fulfillmentDeliveryDetails->setCanceledAt($deliveryDetails->canceled_at);
        $fulfillmentDeliveryDetails->setCancelReason($deliveryDetails->cancel_reason);
        $fulfillmentDeliveryDetails->setCourierPickupAt($deliveryDetails->courier_pickup_at);
        $fulfillmentDeliveryDetails->setCourierPickupWindowDuration($deliveryDetails->courier_pickup_window_duration);
        $fulfillmentDeliveryDetails->setIsNoContactDelivery($deliveryDetails->is_no_contact_delivery);
        $fulfillmentDeliveryDetails->setDropoffNotes($deliveryDetails->dropoff_notes);
        $fulfillmentDeliveryDetails->setCourierProviderName($deliveryDetails->courier_provider_name);
        $fulfillmentDeliveryDetails->setCourierSupportPhoneNumber($deliveryDetails->courier_support_phone_number);
        $fulfillmentDeliveryDetails->setSquareDeliveryId($deliveryDetails->square_delivery_id);
        $fulfillmentDeliveryDetails->setExternalDeliveryId($deliveryDetails->external_delivery_id);
        $fulfillmentDeliveryDetails->setManagedDelivery($deliveryDetails->managed_delivery);

        return $fulfillmentDeliveryDetails;
    }

    /**
     * Builds the fulfillment details for pickup fulfillment types.
     *
     * @param  PickupDetails  $pickupDetails
     * @return FulfillmentPickupDetails
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildPickupDetails(PickupDetails $pickupDetails): FulfillmentPickupDetails
    {
        // Create the square request fulfillment pick
        $fulfillmentPickupDetails = new FulfillmentPickupDetails();

        // Set the recipient
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($pickupDetails->recipient->display_name);
        $recipient->setEmailAddress($pickupDetails->recipient->email_address);
        $recipient->setPhoneNumber($pickupDetails->recipient->phone_number);
        $fulfillmentPickupDetails->setRecipient($recipient);

        $fulfillmentPickupDetails->setExpiresAt($pickupDetails->expires_at);
        $fulfillmentPickupDetails->setAutoCompleteDuration($pickupDetails->auto_complete_duration);
        $fulfillmentPickupDetails->setScheduleType($pickupDetails->schedule_type);
        $fulfillmentPickupDetails->setPickupAt($pickupDetails->pickup_at);
        $fulfillmentPickupDetails->setPickupWindowDuration($pickupDetails->pickup_window_duration);
        $fulfillmentPickupDetails->setPrepTimeDuration($pickupDetails->prep_time_duration);
        $fulfillmentPickupDetails->setNote($pickupDetails->note);
        $fulfillmentPickupDetails->setPlacedAt($pickupDetails->placed_at);
        $fulfillmentPickupDetails->setAcceptedAt($pickupDetails->accepted_at);
        $fulfillmentPickupDetails->setRejectedAt($pickupDetails->rejected_at);
        $fulfillmentPickupDetails->setReadyAt($pickupDetails->ready_at);
        $fulfillmentPickupDetails->setExpiredAt($pickupDetails->expired_at);
        $fulfillmentPickupDetails->setPickedUpAt($pickupDetails->picked_up_at);
        $fulfillmentPickupDetails->setCanceledAt($pickupDetails->canceled_at);
        $fulfillmentPickupDetails->setCancelReason($pickupDetails->cancel_reason);

        // Add curbside pickup details
        $this->addCurbsidePickupDetails($fulfillmentPickupDetails, $pickupDetails);

        return $fulfillmentPickupDetails;
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
     * @param  DeliveryDetails  $shipmentDetails
     * @return FulfillmentShipmentDetails
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildShipmentDetails(ShipmentDetails $shipmentDetails): FulfillmentShipmentDetails
    {
        $fulfillmentShipmentDetails = new FulfillmentShipmentDetails();

        // Set the recipient
        $recipient = new FulfillmentRecipient();
        $recipient->setDisplayName($shipmentDetails->recipient->display_name);
        $recipient->setEmailAddress($shipmentDetails->recipient->email_address);
        $recipient->setPhoneNumber($shipmentDetails->recipient->phone_number);
        $fulfillmentShipmentDetails->setRecipient($recipient);

        $fulfillmentShipmentDetails->setCarrier($shipmentDetails->carrier);
        $fulfillmentShipmentDetails->setShippingNote($shipmentDetails->shipping_note);
        $fulfillmentShipmentDetails->setShippingType($shipmentDetails->shipping_type);
        $fulfillmentShipmentDetails->setTrackingNumber($shipmentDetails->tracking_number);
        $fulfillmentShipmentDetails->setTrackingUrl($shipmentDetails->tracking_url);
        $fulfillmentShipmentDetails->setPlacedAt($shipmentDetails->placed_at);
        $fulfillmentShipmentDetails->setInProgressAt($shipmentDetails->in_progress_at);
        $fulfillmentShipmentDetails->setPackagedAt($shipmentDetails->packaged_at);
        $fulfillmentShipmentDetails->setExpectedShippedAt($shipmentDetails->expected_shipped_at);
        $fulfillmentShipmentDetails->setShippedAt($shipmentDetails->shipped_at);
        $fulfillmentShipmentDetails->setCanceledAt($shipmentDetails->canceled_at);
        $fulfillmentShipmentDetails->setCancelReason($shipmentDetails->cancel_reason);
        $fulfillmentShipmentDetails->setFailedAt($shipmentDetails->failed_at);
        $fulfillmentShipmentDetails->setFailureReason($shipmentDetails->failure_reason);

        return $fulfillmentShipmentDetails;
    }
}
