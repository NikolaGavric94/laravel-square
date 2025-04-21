<?php

namespace Nikolag\Square;

use Nikolag\Core\Abstracts\CorePaymentService;
use Nikolag\Square\Builders\CustomerBuilder;
use Nikolag\Square\Builders\FulfillmentBuilder;
use Nikolag\Square\Builders\OrderBuilder;
use Nikolag\Square\Builders\ProductBuilder;
use Nikolag\Square\Builders\RecipientBuilder;
use Nikolag\Square\Builders\SquareRequestBuilder;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Exceptions\AlreadyUsedSquareProductException;
use Nikolag\Square\Exceptions\InvalidSquareAmountException;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Location;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\ModifierOptionLocationPivot;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\BatchDeleteCatalogObjectsResponse;
use Square\Models\BatchUpsertCatalogObjectsRequest;
use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Models\CatalogModifier;
use Square\Models\CatalogObject;
use Square\Models\CatalogModifierListInfo;
use Square\Models\CatalogModifierListSelectionType;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\Error;
use Square\Models\CreateCatalogImageRequest;
use Square\Models\CreateCatalogImageResponse;
use Square\Models\ListCatalogResponse;
use Square\Models\ListLocationsResponse;
use Square\Models\RetrieveLocationResponse;
use Square\Models\ListPaymentsResponse;
use Square\Models\UpdateCustomerRequest;
use Square\Utils\FileWrapper;
use stdClass;

class SquareService extends CorePaymentService implements SquareServiceContract
{
    /**
     * @var stdClass
     */
    private stdClass $orderCopy;
    /**
     * @var OrderBuilder
     */
    private OrderBuilder $orderBuilder;
    /**
     * @var SquareRequestBuilder
     */
    private SquareRequestBuilder $squareBuilder;
    /**
     * @var ProductBuilder
     */
    private ProductBuilder $productBuilder;
    /**
     * @var CustomerBuilder
     */
    protected CustomerBuilder $customerBuilder;
    /**
     * @var FulfillmentBuilder
     */
    private FulfillmentBuilder $fulfillmentBuilder;
    /**
     * @var RecipientBuilder
     */
    private RecipientBuilder $recipientBuilder;
    /**
     * @var string
     */
    private string $locationId;
    /**
     * @var string
     */
    private string $currency;
    /**
     * @var mixed
     */
    protected mixed $fulfillment = null;
    /**
     * @var mixed
     */
    protected mixed $fulfillmentDetails = null;
    /**
     * @var mixed
     */
    protected mixed $fulfillmentRecipient = null;
    /**
     * @var CreateOrderRequest
     */
    private CreateOrderRequest $createOrderRequest;
    /**
     * @var CreateCustomerRequest
     */
    private CreateCustomerRequest $createCustomerRequest;

    public function __construct(SquareConfig $squareConfig)
    {
        $this->config = $squareConfig;
        $this->orderCopy = new stdClass();
        $this->orderBuilder = new OrderBuilder();
        $this->squareBuilder = new SquareRequestBuilder();
        $this->productBuilder = new ProductBuilder();
        $this->customerBuilder = new CustomerBuilder();
        $this->fulfillmentBuilder = new FulfillmentBuilder();
        $this->recipientBuilder = new RecipientBuilder();
    }

    /**
     * Batch deletes catalog objects.
     *
     * @param array<string> $catalogObjectIds The catalog object IDs to delete.
     *
     * @throws Exception When an error occurs.
     *
     * @return BatchDeleteCatalogObjectsResponse
     */
    public function batchDeleteCatalogObjects(array $catalogObjectIds)
    {
        $request = $this->getSquareBuilder()->buildBatchDeleteCategoryObjectsRequest($catalogObjectIds);

        // Call the Catalog API function batchDeleteCatalogObjects to delete all our items at once.
        $apiResponse = $this->config->catalogAPI()->batchDeleteCatalogObjects($request);

        if ($apiResponse->isSuccess()) {
            /** @var BatchDeleteCatalogObjectsResponse $results */
            $results = $apiResponse->getResult();

            return $results;
        } else {
            throw $this->_handleApiResponseErrors($apiResponse);
        }
    }

    /**
     * Uploads the items, and adds images, when creating new items for the catalog.
     *
     * @param BatchUpsertCatalogObjectsRequest $batchUpsertCatalogRequest The request to upload the items.
     *
     * @throws Exception When an error occurs.
     *
     * @return BatchUpsertCatalogObjectsResponse
     */
    public function batchUpsertCatalog(BatchUpsertCatalogObjectsRequest $batchUpsertCatalogRequest)
    {
        // We call the Catalog API function batchUpsertCatalogObjects to upload all our
        // items at once.
        $apiResponse = $this->config->catalogAPI()->batchUpsertCatalogObjects($batchUpsertCatalogRequest);

        if ($apiResponse->isSuccess()) {
            /** @var BatchUpsertCatalogObjectsResponse $results */
            $results = $apiResponse->getResult();

            return $results;
        } else {
            throw $this->_handleApiResponseErrors($apiResponse);
        }
    }

    /**
     * Creates a catalog image.
     *
     * @param CreateCatalogImageRequest $createCatalogImageRequest The request to create the image.
     * @param string                    $filePath                  The image to upload.
     *
     * @throws Exception When an error occurs.
     *
     * @return CreateCatalogImageResponse
     */
    public function createCatalogImage(
        CreateCatalogImageRequest $createCatalogImageRequest,
        string $filePath
    ) {
        // Check to see if the file exists
        if (!file_exists($filePath)) {
            throw new Exception('The file does not exist');
        }
        // Create a file wrapper
        $fileWrapper = FileWrapper::createFromPath($filePath);

        // Call the Catalog API function createCatalogImage to upload the image
        $apiResponse = $this->config->catalogAPI()->createCatalogImage($createCatalogImageRequest, $fileWrapper);

        if ($apiResponse->isSuccess()) {
            /** @var CreateCatalogImageResponse $results */
            $results = $apiResponse->getResult();

            return $results;
        } else {
            throw $this->_handleApiResponseErrors($apiResponse);
        }
    }

    /**
     * Helper function to get the appropriate currency to be used based on the location ID provided.
     *
     * @param string|null $locationId The location ID.
     *
     *
     * @return string The currency code
     */
    public function getCurrency($locationId = 'main')
    {
        // Get the currency for the location
        return $this->retrieveLocation($locationId)->getLocation()->getCurrency();
    }

    /**
     * Retrieves the Square API request builder.
     *
     * @return SquareRequestBuilder
     */
    public function getSquareBuilder(): SquareRequestBuilder
    {
        return $this->squareBuilder;
    }

    /**
     * List locations.
     *
     * @return ListLocationsResponse
     */
    public function locations(): ListLocationsResponse
    {
        return $this->config->locationsAPI()->listLocations()->getResult();
    }

    /**
     * Retrieves a specific location.
     *
     * @param string $locationId The location ID.
     *
     * @return RetrieveLocationResponse
     *
     * @throws ApiException
     */
    public function retrieveLocation(string $locationId): RetrieveLocationResponse
    {
        return $this->config->locationsAPI()->retrieveLocation($locationId)->getResult();
    }

    /**
     * Lists the entire catalog.
     *
     * @param string $types The types of objects to list.
     *
     * @return array<\Square\Models\CatalogObject> The catalog items.
     *
     * @throws ApiException
     */
    public function listCatalog(?string $types = null): array
    {
        $catalogItems   = [];
        $cursor         = null;
        $pagesRetrieved = 0;

        do {
            $apiResponse = $this->config->catalogApi()->listCatalog($cursor, $types);

            if ($apiResponse->isSuccess()) {
                /** @var ListCatalogResponse $results */
                $results      = $apiResponse->getResult();
                $catalogItems = array_merge($catalogItems, $results->getObjects() ?? []);
                $cursor       = $results->getCursor();
            } else {
                throw $this->handleApiResponseErrors($apiResponse);
            }

            // Increment the pages retrieved
            $pagesRetrieved++;
        } while ($cursor);

        return $catalogItems;
    }

    /**
     * Sync all discounts to the discount table.
     *
     * @return void
     */
    public function syncDiscounts(): void
    {
        // Retrieve the main location (since we're seeding for tests, just base it on the main location)
        /** @var array<CatalogObject> */
        $discountCatalogObjects = self::listCatalog('DISCOUNT');

        foreach ($discountCatalogObjects as $discountObject) {
            $discountData = $discountObject->getDiscountData();
            $itemData = [
                'name' => $discountData->getName(),
                'percentage' => $discountData->getPercentage(),
                'amount' => $discountData->getAmountMoney()?->getAmount(),
            ];

            $squareID = $discountObject->getId();

            // Create or update the product
            Discount::updateOrCreate(['square_catalog_object_id' => $squareID], $itemData);
        }
    }

    /**
     * Syncs all the locations and their data.
     *
     * @return void
     *
     * @throws Exception If an error occurs.
     */
    public function syncLocations()
    {
        // Map the locations to the Location model so we can do one bulk-insert
        $allLocationData = collect($this->locations()->getLocations())->map(function ($location) {
            return Location::processLocationData($location);
        })->toArray();

        foreach ($allLocationData as $locationData) {
            // Create or update the location
            Location::updateOrCreate([
                'square_id' => $locationData['square_id']
            ], $locationData);
        }
    }

    /**
     * Sync all product modifiers and their options to the database.
     *
     * @return void
     */
    public function syncModifiers(): void
    {
        // Retrieve the main location (since we're seeding for tests, just base it on the main location)
        /** @var array<CatalogObject> */
        $modifierListCatalogObjects = self::listCatalog('MODIFIER_LIST');

        foreach ($modifierListCatalogObjects as $modifierListObject) {
            $catalogModifierList = $modifierListObject->getModifierListData();
            $catalogModifierListData = [
                'name' => $catalogModifierList->getName(),
                'ordinal' => $catalogModifierList?->getOrdinal(),
                'selection_type' => $catalogModifierList->getSelectionType() ?? CatalogModifierListSelectionType::MULTIPLE,
                'type' => $catalogModifierList->getModifierType(),
            ];

            $squareID = $modifierListObject->getId();

            // Create or update the product
            $modifierModel = Modifier::updateOrCreate([
                'square_catalog_object_id' => $squareID
            ], $catalogModifierListData);

            $catalogModifiers = $catalogModifierList->getModifiers();
            if (! $catalogModifiers) {
                continue;
            }

            // Sync the modifier options if there are any
            $this->syncModifierOptions($modifierModel);
        }
    }

    /**
     * Sync all modifiers options and their relationships to the database.
     *
     * @param Modifier $modifierModel The modifier model to sync the options for.
     *
     * @return void
     */
    public function syncModifierOptions(Modifier $modifierModel): void
    {
        // Array cache the results of the modifier list so we only make this call once during the sync
        /** @var array<CatalogObject> */
        $modifierCatalogObjects = cache()
            ->store('array')
            ->remember(__METHOD__, now()->addMinutes(1), fn () => self::listCatalog('MODIFIER'));

        // Filter the modifier options to only include the ones that are part of the modifier list
        $modifierCatalogObjects = collect($modifierCatalogObjects)->filter(function ($modifierObject) use ($modifierModel) {
            $modifierData = $modifierObject->getModifierData();

            return $modifierData->getModifierListId() === $modifierModel->square_catalog_object_id;
        });

        foreach ($modifierCatalogObjects as $modifierObject) {
            $catalogModifier = $modifierObject->getModifierData();

            $modifierOptionData = [
                'name' => $catalogModifier->getName(),
                'price_money_amount' => $catalogModifier->getPriceMoney()?->getAmount(),
                'price_money_currency' => $catalogModifier->getPriceMoney()?->getCurrency(),
                'modifier_id' => $modifierModel->id
            ];

            $modifierDataSquareID = $modifierObject->getId();

            // Create or update the product
            $modifierOption = ModifierOption::updateOrCreate([
                'square_catalog_object_id' => $modifierDataSquareID
            ], $modifierOptionData);

            // Determine if there are any location-option overrides
            $locationOverrides = $catalogModifier->getLocationOverrides();
            $absentAtLocations = $modifierObject->getAbsentAtLocationIds();
            if (! $locationOverrides && ! $absentAtLocations) {
                continue;
            }

            // Map the square location IDs to our local location IDs
            $squareLocationIDs = collect($locationOverrides)->map(function ($locationOverride) {
                return $locationOverride->getLocationId();
            })
                // Add the absent at locations to the list
                ->merge(collect($absentAtLocations))
                ->toArray();

            // Get the local location IDs
            $locationIDs = Location::whereIn('square_id', $squareLocationIDs)->pluck('id');

            $insertData = [];
            foreach ($locationIDs as $locationID) {
                $insertData[] = [
                    'modifier_option_id' => $modifierOption->id,
                    'location_id' => $locationID
                ];
            }

            // Create new location overrides
            ModifierOptionLocationPivot::insert($insertData);
        }
    }

    /**
     * Sync all products and their variations to the products table.
     *
     * @return void
     */
    public function syncProducts(): void
    {
        // Retrieve the main location (since we're seeding for tests, just base it on the main location)
        /** @var array<CatalogObject> */
        $itemCatalogObjects = self::listCatalog('ITEM');

        foreach ($itemCatalogObjects as $itemObject) {
            $itemData = $itemObject->getItemData();

            // Check for modifier data
            $modifierListInfo = $itemData->getModifierListInfo();

            // Sync the variations to the database
            foreach ($itemData->getVariations() as $variation) {
                $variationItemData = [
                    'name'           => $itemData->getName(),
                    'description'    => $itemData->getDescriptionHtml(),
                    'variation_name' => $variation->getItemVariationData()->getName(),
                    'description'    => $itemData->getDescription(),
                    'price'          => $variation->getItemVariationData()->getPriceMoney()?->getAmount(),
                ];

                $squareID = $variation->getId();

                // Create or update the product
                $product = Product::updateOrCreate(['square_catalog_object_id' => $squareID], $variationItemData);

                // Check for modifier data for this specific product
                if ($modifierListInfo) {
                    $this->syncProductModifiers($product, $modifierListInfo);
                }
            }
        }
    }

    /**
     * Sync a given product and it's modifiers.
     *
     * @param Product $product The product model to sync the modifiers for.
     * @param CatalogModifierListInfo[] $modifierListInfo The modifier list info for the product.
     *
     * @return void
     */
    public function syncProductModifiers(Product $product, array $modifierListInfo): void
    {
        foreach ($modifierListInfo as $modifierList) {
            $modifierListID = $modifierList->getModifierListId();
            $modifier = Modifier::where('square_catalog_object_id', $modifierList->getModifierListId())->first();
            if (!$modifier) {
                throw new Exception(
                    "Modifier list ID: $modifierListID not found during product sync for product ID: $product->id"
                );
            }

            // If the pivot table link already exists, skip
            if ($product->modifiers->contains($modifier->id)) {
                continue;
            }

            // Attach the modifier to the product
            $product->modifiers()->attach($modifier->id);
        }
    }

    /**
     * Sync all taxes to the taxes table.
     *
     * @return void
     */
    public function syncTaxes(): void
    {
        // Retrieve the main location (since we're seeding for tests, just base it on the main location)
        /** @var array<CatalogObject> */
        $taxCatalogObjects = self::listCatalog('TAX');

        foreach ($taxCatalogObjects as $taxObject) {
            $taxData = $taxObject->getTaxData();

            $itemData = [
                'name'       => $taxData->getName(),
                'type'       => $taxData->getInclusionType(),
                'percentage' => $taxData->getPercentage(),
            ];

            $squareID = $taxObject->getId();

            // Create or update the product
            Tax::updateOrCreate(['square_catalog_object_id' => $squareID], $itemData);
        }
    }

    /**
     * Save a customer.
     *
     * @return void
     *
     * @throws Exception|ApiException
     */
    private function _saveCustomer(): void
    {
        if (! $this->getCustomer()->payment_service_id) {
            $response = $this->config->customersAPI()->createCustomer($this->getCreateCustomerRequest());

            if ($response->isSuccess()) {
                $this->getCustomer()->payment_service_id = $response->getResult()->getCustomer()->getId();
            } else {
                throw $this->_handleApiResponseErrors($response);
            }
        } else {
            $response = $this->config->customersAPI()->updateCustomer($this->getCustomer()->payment_service_id, $this->getCreateCustomerRequest());

            if ($response->isError()) {
                throw $this->_handleApiResponseErrors($response);
            }
        }

        $this->getCustomer()->save();
        // If merchant exists and if merchant doesn't have customer
        if ($this->getMerchant() && ! $this->getMerchant()->hasCustomer($this->getCustomer()->email)) {
            // Attach seller to the buyer
            $this->getCustomer()->merchants()->attach($this->getMerchant()->id);
        }
    }

    /**
     * Save order to database and if required
     * also save to square vault.
     *
     * @param  bool  $saveToSquare
     * @return void
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     * @throws Exception
     * @throws ApiException
     */
    private function _saveOrder(bool $saveToSquare = false): void
    {
        //If property locationId doesn't exist throw error
        if (! $this->locationId) {
            throw new MissingPropertyException('$locationId property is missing', 500);
        }
        // Add location id to the order copy
        $this->orderCopy->location_id = $this->locationId;

        $this->order = $this->orderBuilder->buildOrderFromOrderCopy($this->getOrder(), $this->orderCopy);

        //If order doesn't have any products throw error
        if ($this->getOrder()->products()->count() == 0) {
            throw new InvalidSquareOrderException('Object Order must have at least 1 Product', 500);
        }
        //If local order doesn't have square order identifier to which to relate
        //local order
        $property = config('nikolag.connections.square.order.service_identifier');
        if (! $this->getOrder()->hasColumn($property)) {
            throw new InvalidSquareOrderException('Table orders is missing a required column: '.$property, 500);
        }
        $orderRequest = $this->squareBuilder->buildOrderRequest($this->getOrder(), $this->locationId, $this->currency);
        $this->setCreateOrderRequest($orderRequest);
        // If want to save to square, make a request
        if ($saveToSquare) {
            $response = $this->config->ordersAPI()->createOrder($this->getCreateOrderRequest());
            if ($response->isError()) {
                throw $this->_handleApiResponseErrors($response);
            }
            //Save id of a real order inside of Square to our local model for future use
            $this->getOrder()->{$property} = $response->getResult()->getOrder()->getId();
        }
        $this->getOrder()->save();
    }

    /**
     * @param  ApiResponse  $response
     * @return Exception
     */
    private function _handleApiResponseErrors(ApiResponse $response): Exception
    {
        $errors = $response->getErrors();
        $firstError = array_shift($errors);
        $mapFunc = fn ($error) => new Exception($error->getCategory().': '.$error->getDetail(), $response->getStatusCode());
        $exception = new Exception($firstError->getCategory().': '.$firstError->getDetail(), $response->getStatusCode());

        return $exception->setAdditionalExceptions(array_map($mapFunc, $errors));
    }

    /**
     * Save collected data.
     *
     * @return self
     *
     * @throws Exception on non-2xx response
     */
    public function save(): static
    {
        try {
            if ($this->getCustomer()) {
                $this->_saveCustomer();
            }
            if ($this->getOrder()) {
                $this->_saveOrder();
            }
        } catch (MissingPropertyException $e) {
            $message = 'Required fields are missing: '.$e->getMessage();
            throw new MissingPropertyException($message, 500, $e);
        } catch (InvalidSquareOrderException $e) {
            throw new MissingPropertyException('Invalid order data', 500, $e);
        } catch (Exception|ApiException $e) {
            $apiErrorMessage = $e->getMessage();
            throw new Exception('There was an error with the api request: '.$apiErrorMessage, 500, $e);
        }

        return $this;
    }

    /**
     * Updates order on Square using local data.
     *
     * @return void
     */
    public function saveToSquare(): void
    {
        $this->_saveOrder(true);
    }

    /**
     * Charge a customer.
     *
     * @param  array  $options
     * @return Transaction
     *
     * @throws ApiException
     * @throws Exception on non-2xx response
     * @throws InvalidSquareAmountException
     * @throws MissingPropertyException
     */
    public function charge(array $options): Transaction
    {
        $location_id = array_key_exists('location_id', $options) ? $options['location_id'] : null;
        $currency = array_key_exists('currency', $options) ? $options['currency'] : 'USD';
        $prepData = [
            'idempotency_key' => uniqid(),
            'amount_money' => [
                'amount' => $options['amount'],
                'currency' => $currency,
            ],
            'autocomplete' => true,
            'source_id' => $options['source_id'],
            'location_id' => $location_id,
            'note' => array_key_exists('note', $options) ? $options['note'] : null,
            'reference_id' => array_key_exists('reference_id', $options) ? (string) $options['reference_id'] : null,
        ];

        if (array_key_exists('verification_token', $options) && is_string($options['verification_token'])) {
            $prepData['verification_token'] = $options['verification_token'];
        }

        // Location id is now mandatory to know under which Location we are doing a charge on
        if (! $prepData['location_id']) {
            throw new MissingPropertyException('Required field \'location_id\' is missing', 500);
        }

        $transaction = new Transaction(['status' => Constants::TRANSACTION_STATUS_OPENED, 'amount' => $options['amount'], 'currency' => $currency]);
        // Save and attach merchant
        if ($this->getMerchant()) {
            $transaction->merchant()->associate($this->getMerchant());
        }
        // Save and attach customer
        if ($this->getCustomer()) {
            try {
                $this->_saveCustomer();
            } catch (Exception $e) {
                $apiErrorMessage = $e->getMessage();
                throw new Exception('There was an error with the api request: '.$apiErrorMessage, 500, $e);
            }
            // Save customer into the table for further use
            $transaction->customer()->associate($this->getCustomer());
            // Set customer id for square from model
            $prepData['customer_id'] = $this->getCustomer()->payment_service_id;
        }
        // Save and attach order
        if ($this->getOrder()) {
            try {
                // Calculate the total order amount
                $calculatedCost = Util::calculateTotalOrderCost($this->orderCopy);
                // If order total does not match charge amount, throw error
                if ($calculatedCost != $options['amount']) {
                    throw new InvalidSquareAmountException('The charge amount does not match the order total.', 500);
                }
                // Save order to both database and square
                $this->_saveOrder(true);
                // Connect order with transaction
                $transaction->order()->associate($this->getOrder());
                // Get table column name for square id property
                $property = config('nikolag.connections.square.order.service_identifier');
                // Set order id for square from order model property for square identifier
                $prepData['order_id'] = $this->getOrder()->{$property};
            } catch (MissingPropertyException $e) {
                throw new MissingPropertyException('Required field is missing', 500, $e);
            } catch (InvalidSquareOrderException $e) {
                throw new MissingPropertyException('Invalid order data', 500, $e);
            } catch (Exception $e) {
                $apiErrorMessage = $e->getMessage();
                throw new Exception('There was an error with the api request: '.$apiErrorMessage, 500, $e);
            }
        }
        $transaction->save();

        $chargeRequest = $this->squareBuilder->buildChargeRequest($prepData);
        $response = $this->config->paymentsAPI()->createPayment($chargeRequest);

        if ($response->isSuccess()) {
            $response = $response->getResult()->getPayment();

            $transaction->payment_service_id = $response->getId();
            $transaction->status = Constants::TRANSACTION_STATUS_PASSED;
            $transaction->save();
        } elseif ($response->isError()) {
            $transaction->payment_service_id = null;
            $transaction->status = Constants::TRANSACTION_STATUS_FAILED;
            $transaction->save();

            throw $this->_handleApiResponseErrors($response);
        }

        return $transaction;
    }

    /**
     * Payments directly from Square API.
     * Please check: https://developer.squareup.com/reference/square/payments-api/list-payments#query-parameters
     * for options that you can pass to this function.
     *
     * @param  array  $options
     * @return ListPaymentsResponse
     *
     * @throws ApiException
     */
    public function payments(array $options): ListPaymentsResponse
    {
        $options = [
            'location_id' => array_key_exists('location_id', $options) ? $options['location_id'] : null,
            'begin_time' => array_key_exists('begin_time', $options) ? $options['begin_time'] : null,
            'end_time' => array_key_exists('end_time', $options) ? $options['end_time'] : null,
            'sort_order' => array_key_exists('sort_order', $options) ? $options['sort_order'] : null,
            'cursor' => array_key_exists('cursor', $options) ? $options['cursor'] : null,
            'total' => array_key_exists('total', $options) ? $options['total'] : null,
            'last_4' => array_key_exists('last_4', $options) ? $options['last_4'] : null,
            'card_brand' => array_key_exists('card_brand', $options) ? $options['card_brand'] : null,
        ];

        return $this->config->paymentsAPI()->listPayments(
            $options['begin_time'],
            $options['end_time'],
            $options['sort_order'],
            $options['cursor'],
            $options['location_id'] ?? $this->locationId,
            $options['total'],
            $options['last_4'],
            $options['card_brand'])->getResult();
    }

    /**
     * Add a fulfillment to the order.
     * NOTE: This currently supports ONE fulfillment per order.  While the Square API supports multiple fulfillments per
     * order, the standard UI does not, so this is limited to a single fulfillment.
     *
     * @param  mixed  $fulfillment
     * @param  string  $type
     * @return self
     *
     * @throws Exception If the order already has a fulfillment.
     */
    public function setFulfillment(mixed $fulfillment): static
    {
        // Fulfillment class
        $fulfillmentClass = Constants::FULFILLMENT_NAMESPACE;

        // Validate the order exists
        if (! $this->getOrder()) {
            throw new InvalidSquareOrderException('Fulfillment cannot be set without an order.', 500);
        }

        if (is_a($fulfillment, $fulfillmentClass)) {
            $this->fulfillment = $this->fulfillmentBuilder->createFulfillmentFromModel(
                $fulfillment,
                $this->getOrder(),
            );
        } else {
            $this->fulfillment = $this->fulfillmentBuilder->createFulfillmentFromArray(
                $fulfillment,
                $this->getOrder(),
            );
        }

        // Check if order already has this fulfillment
        if (! Util::hasFulfillment($this->orderCopy->fulfillments, $this->getFulfillment())) {
            // Add the fulfillment to the order
            $this->orderCopy->fulfillments->push($this->getFulfillment());
        } else {
            throw new InvalidSquareOrderException('This order already has a fulfillment', 500);
        }

        return $this;
    }

    /**
     * Add a recipient to the fulfillment details.
     * NOTE: This currently supports ONE recipient per fulfillment.  While the Square API supports multiple fulfillments
     * which would allow multiple recipients, the standard UI does not, so this is limited to a single recipient.
     *
     * @param  mixed  $recipient
     * @return self
     *
     * @throws Exception If the order's fulfillment details already has a recipient.
     */
    public function setFulfillmentRecipient(mixed $recipient): static
    {
        // Make sure we have a fulfillment
        if (! $this->getFulfillment()) {
            throw new MissingPropertyException('Fulfillment must be added before adding a fulfillment recipient', 500);
        }

        $recipientClass = Constants::RECIPIENT_NAMESPACE;

        if (is_a($recipient, $recipientClass)) {
            $this->fulfillmentRecipient = $this->recipientBuilder->load($recipient->toArray());
        } elseif (is_array($recipient)) {
            $this->fulfillmentRecipient = $this->recipientBuilder->load($recipient);
        }

        // Check if this order's fulfillment details already has a recipient
        if (! $this->getFulfillmentDetails()->recipient) {
            $this->orderCopy->fulfillments->first()->fulfillmentDetails->recipient = $this->getFulfillmentRecipient();
        } else {
            throw new Exception('This order\'s fulfillment details already has a recipient', 500);
        }

        return $this;
    }

    /**
     * Add a product to the order.
     *
     * @param  mixed  $product
     * @param  int  $quantity
     * @param  string  $currency
     * @param array  $modifiers
     * @return self
     *
     * @throws AlreadyUsedSquareProductException
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function addProduct(mixed $product, int $quantity = 1, string $currency = 'USD', array $modifiers = []): static
    {
        //Product class
        $productClass = Constants::PRODUCT_NAMESPACE;

        try {
            if (is_a($product, $productClass)) {
                $productPivot = $this->productBuilder->addProductFromModel($this->getOrder(), $product, $quantity, $modifiers);
            } else {
                $productPivot = $this->productBuilder->addProductFromArray($this->orderCopy, $this->getOrder(), $product, $quantity);
            }
            // Check if order already has this product
            if (! Util::hasProduct($this->orderCopy->products, $productPivot->product)) {
                $this->orderCopy->products->push($productPivot);
            } else {
                throw new AlreadyUsedSquareProductException('Product is already part of the order', 500);
            }
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required field is missing', 500, $e);
        }

        return $this;
    }

    /**
     * @return CreateCustomerRequest|UpdateCustomerRequest
     */
    public function getCreateCustomerRequest(): UpdateCustomerRequest|CreateCustomerRequest
    {
        return $this->createCustomerRequest;
    }

    /**
     * @return mixed
     */
    public function getFulfillment(): mixed
    {
        return $this->fulfillment;
    }

    /**
     * @return mixed
     */
    public function getFulfillmentDetails(): mixed
    {
        return $this->fulfillment->fulfillmentDetails;
    }

    /**
     * @return mixed
     */
    public function getFulfillmentRecipient(): mixed
    {
        return $this->fulfillmentRecipient;
    }

    /**
     * @param  CreateCustomerRequest|UpdateCustomerRequest  $createCustomerRequest
     * @return self
     */
    public function setCreateCustomerRequest($createCustomerRequest): static
    {
        $this->createCustomerRequest = $createCustomerRequest;

        return $this;
    }

    /**
     * @return CreateOrderRequest
     */
    public function getCreateOrderRequest(): CreateOrderRequest
    {
        return $this->createOrderRequest;
    }

    /**
     * @param  CreateOrderRequest  $createOrderRequest
     * @return self
     */
    public function setCreateOrderRequest(CreateOrderRequest $createOrderRequest): static
    {
        $this->createOrderRequest = $createOrderRequest;

        return $this;
    }

    /**
     * @param  mixed  $customer
     * @return self
     *
     * @throws MissingPropertyException
     */
    public function setCustomer(mixed $customer): static
    {
        $customerClass = Constants::CUSTOMER_NAMESPACE;

        if (is_a($customer, $customerClass)) {
            $this->customer = $this->customerBuilder->load($customer->toArray());
        } elseif (is_array($customer)) {
            $this->customer = $this->customerBuilder->load($customer);
        }

        if ($customer) {
            $customerRequest = $this->squareBuilder->buildCustomerRequest($this->customer);
            $this->setCreateCustomerRequest($customerRequest);
        }

        return $this;
    }

    /**
     * Setter for order.
     *
     * @param  mixed  $order
     * @param  string  $locationId
     * @param  string  $currency
     * @return self
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function setOrder(mixed $order, string $locationId, string $currency = 'USD'): static
    {
        //Order class
        $orderClass = config('nikolag.connections.square.order.namespace');

        if (! $order) {
            throw new MissingPropertyException('$order property is missing', 500);
        }
        if (! $locationId) {
            throw new MissingPropertyException('$locationId property is missing', 500);
        }

        $this->locationId = $locationId;
        $this->currency = $currency;

        if (is_a($order, $orderClass)) {
            $this->order = $order;
            $this->orderCopy = $this->orderBuilder->buildOrderCopyFromModel($order);
        } elseif (is_array($order)) {
            $this->order = $this->orderBuilder->buildOrderModelFromArray($order, new $orderClass());
            $this->orderCopy = $this->orderBuilder->buildOrderCopyFromArray($order);
        } else {
            throw new InvalidSquareOrderException('Order must be an instance of '.$orderClass.' or an array', 500);
        }

        return $this;
    }
}
