<?php

namespace Nikolag\Square\Builders;

use Exception;
use Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Builders\SquareRequestBuilders\FulfillmentRequestBuilder;
use Nikolag\Square\Builders\Validate;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\Product;
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
use Square\Models\OrderLineItemModifier;

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
     * Fulfillment request helper builder.
     *
     * @var FulfillmentRequestBuilder
     */
    private FulfillmentRequestBuilder $fulfillmentRequestBuilder;

    /**
     * SquareRequestBuilder constructor.
     */
    public function __construct()
    {
        $this->productTaxes = collect([]);
        $this->productDiscounts = collect([]);

        $this->fulfillmentRequestBuilder = new FulfillmentRequestBuilder();
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
        Validate::validateRequiredFields($data, ['id', 'name']);
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
        Validate::validateRequiredFields($data, ['name', 'tax_ids', 'description', 'variations']);
        $name        = $data['name'];
        $taxIDs      = $data['tax_ids'];
        $description = $data['description'];
        $variations  = $data['variations'];

        // Get the optional fields
        $categoryID   = $data['category_id'] ?? null;
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

        return CatalogObjectBuilder::init(CatalogObjectType::ITEM, '#' . $name)
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
        Validate::validateRequiredFields($data, ['name', 'percentage']);
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
        Validate::validateRequiredFields($data, ['amount', 'currency']);
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
        Validate::validateRequiredFields($data, ['name', 'variation_id', 'item_id', 'price_money']);
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
        Validate::validateRequiredFields($data, ['catalog_object_id' ]);
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

        // Set an order id (this, along with a fulfillment is required for Orders to appear in the Square Dashboard)
        if (array_key_exists('order_id', $prepData)) {
            $request->setOrderId($prepData['order_id']);
        }

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
        $squareOrder->setFulfillments($this->fulfillmentRequestBuilder->buildFulfillments($order->fulfillments));
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
                $tempDiscount->setCatalogObjectId($discount->square_catalog_object_id);

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
     * Builds the modifiers for the order.
     *
     * @param Collection $modifiers
     * @return array
     */
    public function buildModifiers(Collection $modifiers): array
    {
        $temp = [];
        if ($modifiers->isEmpty()) {
            return $temp;
        }

        foreach ($modifiers as $modifier) {
            $tempModifier = new OrderLineItemModifier();
            $tempModifier->setUid(Util::uid());
            $tempModifier->setCatalogObjectId($modifier->modifiable->square_catalog_object_id);

            // NOTE: The text modifiers are added in the setNote method using the buildNotes method below.
            // This comment acts as a placeholder in case this support is added to Square's APIs:
            // // Add text for free text modifiers
            // if (
            //     $modifier->modifiable_type == Modifier::class
            //     && $modifier->modifiable->type == 'TEXT'
            // ) {
            //     // Text based modifiers are not yet supported by Square's APIs:
            //     // https://developer.squareup.com/forums/t/adding-a-text-modifier-via-orders-api/20465/3
            //     // Theoretical placeholder: $tempModifier->setName($modifier->text);
            // }

            // Add the quantity
            $tempModifier->setQuantity($modifier->quantity);

            $temp[] = $tempModifier;
        }

        return $temp;
    }

    /**
     * Builds the note for the line-item.
     *
     * @param Product $product
     * @param Collection $modifiers
     * @return string
     */
    public function buildNote(Product $product, Collection $modifiers): string
    {
        $note = $product->note ?? '';

        foreach ($modifiers as $modifier) {
            if ($modifier->modifiable_type == Modifier::class && $modifier->modifiable->type == 'TEXT') {
                $note .= ' ' . $modifier->text;
            }
        }

        return $note;
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
                $tempTax->setCatalogObjectId($tax->square_catalog_object_id);
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
                $money->setAmount($product->pivot->price_money_amount);
                $money->setCurrency($currency);
                $tempProduct = new OrderLineItem($quantity);
                $tempProduct->setName($product->name);
                $tempProduct->setBasePriceMoney($money);
                $tempProduct->setQuantity((string) $quantity);
                $tempProduct->setCatalogObjectId($product->square_catalog_object_id);
                $tempProduct->setVariationName($product->variation_name);
                $tempProduct->setNote($this->buildNote($product, $pivotProduct->modifiers));
                $tempProduct->setModifiers($this->buildModifiers($pivotProduct->modifiers));
                $tempProduct->setAppliedDiscounts($this->buildAppliedDiscounts($discounts));
                $tempProduct->setAppliedTaxes($this->buildAppliedTaxes($taxes));
                $temp[] = $tempProduct;
            }
        }

        return $temp;
    }
}
