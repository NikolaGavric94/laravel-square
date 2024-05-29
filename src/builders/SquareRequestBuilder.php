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
use Square\Models\BatchDeleteCatalogObjectsRequest;
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
use Square\Models\Builders\BatchDeleteCatalogObjectsRequestBuilder;
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
     * Validates that the required fields are present in the data array.
     *
     * @param array $data
     * @param array $requiredFields
     *
     * @throws MissingPropertyException
     *
     * @return void
     */
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || empty($data[$field])) {
                throw new MissingPropertyException("The $field field is required", 500);
            }
        }
    }

    /**
     * Builds a batch delete category objects request
     *
     * @param array<string> $catalogObjectIds The catalog object IDs to delete.
     *
     * @return BatchDeleteCatalogObjectsRequest
     */
    public function buildBatchDeleteCategoryObjectsRequest(array $catalogObjectIds): BatchDeleteCatalogObjectsRequest
    {
        return BatchDeleteCatalogObjectsRequestBuilder::init()
                ->objectIds($catalogObjectIds)
                ->build();
    }

    /**
     * Builds a category catalog object item.
     *
     * @param array $data
     *
     * @return CatalogObject
     */
    public function buildCategoryCatalogObject(array $data): CatalogObject
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['id', 'name']);
        $id   = $data['id'];
        $name = $data['name'];

        // Get the optional fields
        $allLocations = $data['all_locations'] ?? true;

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
     * @param array $data
     *
     * @return CatalogObject
     */
    public function buildItemCatalogObject(array $data): CatalogObject
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['name', 'tax_ids', 'description', 'variations']);
        $name        = $data['name'];
        $taxIDs      = $data['tax_ids'];
        $description = $data['description'];
        $variations  = $data['variations'];

        // Get the optional fields
        $categoryID  = $data['category_id'] ?? null;
        $allLocations = $data['all_locations'] ?? true;

        // Create a catalog item builder
        $catalogItemBuilder = CatalogItemBuilder::init()
            ->name($name)
            ->taxIds($taxIDs)
            ->variations($variations);

        // Add the category ID to the catalog item builder
        if (!empty($categoryID)) {
            $catalogItemBuilder->categoryId($categoryID);
        }

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
     * @param array $data
     *
     * @return CatalogObject
     */
    public function buildTaxCatalogObject(array $data): CatalogObject
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['name', 'percentage']);
        $name       = $data['name'];
        $percentage = $data['percentage'];

        // Get the optional fields
        $calculationPhase       = $data['calculation_phase'] ?? TaxCalculationPhase::TAX_TOTAL_PHASE;
        $inclusionType          = $data['inclusion_type'] ?? TaxInclusionType::ADDITIVE;
        $appliesToCustomAmounts = $data['applies_to_custom_amounts'] ?? true;
        $enabled                = $data['enabled'] ?? true;
        $allLocations           = $data['all_locations'] ?? true;

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
     * @param array $data
     *
     * @return Money
     */
    public function buildMoney(array $data): Money
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['amount', 'currency']);
        $amount   = $data['amount'];
        $currency = $data['currency'];

        return MoneyBuilder::init()
            ->amount($amount)
            ->currency($currency)
            ->build();
    }

    /**
     * Builds a variation catalog object item.
     *
     * @param array $data
     *
     * @return CatalogObject
     */
    public function buildVariationCatalogObject(array $data): CatalogObject
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['name', 'variation_id', 'item_id', 'price_money']);
        $name        = $data['name'];
        $variationID = $data['variation_id'];
        $itemID      = $data['item_id'];
        $priceMoney  = $data['price_money'];
        if (!$priceMoney instanceof Money) {
            throw new MissingPropertyException('The price_money field must be an instance of Money', 500);
        }

        // Get the optional fields
        $pricingType  = $data['pricing_type'] ?? CatalogPricingType::FIXED_PRICING;
        $allLocations = $data['all_locations'] ?? true;

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
     * @param array $data
     *
     * @return CreateCatalogImageRequest
     */
    public function buildCatalogImageRequest(array $data): CreateCatalogImageRequest
    {
        // Get the required fields
        $this->validateRequiredFields($data, ['catalog_object_id' ]);
        $catalogObjectId = $data['catalog_object_id'];

        // Get the optional fields
        $caption   = $data['caption'] ?? null;
        $isPrimary = $data['is_primary'] ?? true;

        // Create the catalog image request builder
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
        $tempFulfillment = null;
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
            }
        }

        // Only one fulfillment per order is supported
        return [$tempFulfillment];
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
     * @param  ShipmentDetails  $shipmentDetails
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
