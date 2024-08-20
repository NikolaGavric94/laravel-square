<?php

namespace Nikolag\Square\Tests\Unit;

use Str;
use Nikolag\Square\Builders\SquareRequestBuilder;
use Nikolag\Square\Exception;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\BatchUpsertCatalogObjectsRequest;
use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Utils\FileWrapper;

class SquareServiceTest extends TestCase
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
     * Tests the batchUpsertCatalogObjectsRequest
     *
     * @return void
     */
    public function test_batch_delete_catalog_objects()
    {
        // The request below will be invalid, so make sure it throws an exception.
        $this->expectException(Exception::class);

        // Call the method we're testing
        Square::batchDeleteCatalogObjects([]);
    }

    /**
     * Tests the batchUpsertCatalogObjectsRequest
     *
     * @return void
     */
    public function test_batch_upsert_catalog()
    {
        // Use the BatchUpsertCatalogObjectsRequestBuilder to create the request.
        $request = \Square\Models\Builders\BatchUpsertCatalogObjectsRequestBuilder::init(
            (string) Str::uuid(),
            [\Square\Models\Builders\CatalogObjectBatchBuilder::init([])->build()]
        )->build();

        // The request below will be invalid, so make sure it throws an exception.
        $this->expectException(Exception::class);

        // Call the method we're testing
        Square::batchUpsertCatalog($request);
    }

    /**
     * Tests the buildBatchDeleteCategoryObjectsRequest method.
     *
     * @return void
     */
    public function test_build_batch_delete_category_objects_request(): void
    {
        $catalogObjectID = 'Catalog Object ID';

        // Build the image request
        $batchDeleteRequest = Square::getSquareBuilder()->buildBatchDeleteCategoryObjectsRequest([
            $catalogObjectID
        ]);

        $this->assertNotNull($batchDeleteRequest);
        $this->assertInstanceOf(\Square\Models\BatchDeleteCatalogObjectsRequest::class, $batchDeleteRequest);

        $this->assertEquals([$catalogObjectID], $batchDeleteRequest->getObjectIds());
    }

    /**
     * Tests the buildCatalogImageRequest method.
     *
     * @return void
     */
    public function test_build_catalog_image_request(): void
    {
        // Set up the variables
        $catalogObjectID = 'Catalog Object ID';
        $caption         = 'Test Caption';

        // Build the image request
        $imageRequest = Square::getSquareBuilder()->buildCatalogImageRequest([
            'catalog_object_id' => $catalogObjectID,
            'caption'           => $caption
        ]);

        $this->assertNotNull($imageRequest);
        $this->assertInstanceOf(\Square\Models\CreateCatalogImageRequest::class, $imageRequest);

        $this->assertNotNull($imageRequest->getIdempotencyKey());
        $this->assertEquals($catalogObjectID, $imageRequest->getObjectId());
        $this->assertInstanceOf(\Square\Models\CatalogObject::class, $imageRequest->getImage());
        $this->assertEquals($caption, $imageRequest->getImage()->getImageData()->getCaption());
        $this->assertTrue($imageRequest->getIsPrimary());
    }

    /**
     * Tests the buildCategoryCatalogObject method.
     *
     * @return void
     */
    public function test_build_category_catalog_object(): void
    {
        // Set up the variables
        $id   = 1;
        $name = 'Test Category Description';

        // Build the category object
        $category = Square::getSquareBuilder()->buildCategoryCatalogObject([
            'id'   => $id,
            'name' => $name
        ]);

        $this->assertNotNull($category);
        $this->assertInstanceOf(\Square\Models\CatalogObject::class, $category);
        $this->assertEquals('CATEGORY', $category->getType());
        $this->assertEquals($id, $category->getId());
        $this->assertEquals($name, $category->getCategoryData()->getName());
    }

    /**
     * Tests the buildItemCatalogObject method.
     *
     * @return void
     */
    public function test_build_item_catalog_object(): void
    {
        // Set up the variables
        $name        = 'Test Item Name';
        $taxIDs      = [1, 2, 3];
        $description = 'Test Item Description';
        $money       = Square::getSquareBuilder()->buildMoney([
            'amount'   => 1000,
            'currency' => Square::getCurrency()
        ]);

        // First, create the default variation and category objects
        $variation = Square::getSquareBuilder()->buildVariationCatalogObject([
            'name'         => 'Variation Name',
            'variation_id' => 'Variation #1',
            'item_id'      => 'Item ID',
            'price_money'  => $money
        ]);
        $category  = Square::getSquareBuilder()->buildCategoryCatalogObject([
            'id'   => 1,
            'name' => 'Category Name',
        ]);

        // Build the item object
        $item = Square::getSquareBuilder()->buildItemCatalogObject([
            'name'        => $name,
            'tax_ids'     => $taxIDs,
            'description' => $description,
            'variations'  => [$variation],
            'category_id' => $category->getId()
        ]);

        $this->assertNotNull($item);
        $this->assertInstanceOf(\Square\Models\CatalogObject::class, $item);
        $this->assertEquals('ITEM', $item->getType());
        // Make sure the ID is the name with a preceding "#" character
        $this->assertEquals('#' . $name, $item->getId());
        $this->assertEquals($name, $item->getItemData()->getName());
        $this->assertEquals($taxIDs, $item->getItemData()->getTaxIds());
        $this->assertEquals($description, $item->getItemData()->getDescription());
        $this->assertEquals($variation, $item->getItemData()->getVariations()[0]);
        $this->assertEquals($category->getId(), $item->getItemData()->getCategoryId());
    }

    /**
     * Tests the buildTaxCatalogObject method.
     *
     * @return void
     */
    public function test_build_tax_catalog_object(): void
    {
        // Set up the variables
        $name = 'Test Tax Description';
        $rate = 0.1;

        // Build the tax object
        $tax = Square::getSquareBuilder()->buildTaxCatalogObject([
            'name'       => $name,
            'percentage' => $rate
        ]);

        $this->assertNotNull($tax);
        $this->assertInstanceOf(\Square\Models\CatalogObject::class, $tax);
        $this->assertEquals('TAX', $tax->getType());
        $this->assertEquals($name, $tax->getTaxData()->getName());
        $this->assertEquals($rate, $tax->getTaxData()->getPercentage());
    }

    /**
     * Tests the buildVariationCatalogObject method.
     *
     * @return void
     */
    public function test_build_variation_catalog_object(): void
    {
        // Set up the variables
        $id     = 'Variation #1';
        $name   = 'Test Item Description';
        $itemID = 'Item #1';
        $money       = Square::getSquareBuilder()->buildMoney([
            'amount'   => 1000,
            'currency' => Square::getCurrency()
        ]);

        // Build the item object
        $item = Square::getSquareBuilder()->buildVariationCatalogObject([
            'name'         => $name,
            'variation_id' => $id,
            'item_id'      => $itemID,
            'price_money'  => $money
        ]);

        $this->assertNotNull($item);
        $this->assertInstanceOf(\Square\Models\CatalogObject::class, $item);
        $this->assertEquals('ITEM_VARIATION', $item->getType());
        $this->assertEquals($id, $item->getId());
        $this->assertEquals($name, $item->getItemVariationData()->getName());
        $this->assertEquals($itemID, $item->getItemVariationData()->getItemId());
        $this->assertEquals($money, $item->getItemVariationData()->getPriceMoney());
    }

    /**
     * Tests the buildMoney method.
     *
     * @return void
     */
    public function test_build_money(): void
    {
        $amount   = 1000;
        $currency = 'USD';
        $money    = Square::getSquareBuilder()->buildMoney([
            'amount'   => $amount,
            'currency' => $currency
        ]);

        $this->assertNotNull($money);
        $this->assertInstanceOf(\Square\Models\Money::class, $money);
        $this->assertEquals($amount, $money->getAmount());
        $this->assertEquals($currency, $money->getCurrency());
    }

    /**
     * Tests the createCatalogImage method.
     *
     * @return void
     */
    public function test_create_catalog_image(): void
    {
        // Create a mocked file
        $fileName = 'image.jpg';
        $file     = \Illuminate\Http\UploadedFile::fake()->create($fileName, 100);
        $filePath = $file->getPathname();

        $request = Square::getSquareBuilder()->buildCatalogImageRequest([
            'catalog_object_id' => 'Fake ID',
            'caption'           => 'Test caption'
        ]);

        // The request below will be invalid, so make sure it throws an exception.
        $this->expectException(Exception::class);

        // Call the method we're testing
        $result = Square::createCatalogImage($request, $filePath);
    }

    /**
     * Tests the getCurrency method.
     *
     * @return void
     */
    public function test_get_currency(): void
    {
        $currency = Square::getCurrency();

        $this->assertNotNull($currency);
        $this->assertEquals('USD', $currency);
    }

    /**
     * Returns the square request builder.
     *
     * @return void
     */
    public function test_get_square_builder(): void
    {
        $builder = Square::getSquareBuilder();

        $this->assertNotNull($builder);
        $this->assertInstanceOf('\Nikolag\Square\Builders\SquareRequestBuilder', $builder);
    }

    /**
     * Charge OK.
     *
     * @return void
     */
    public function test_square_charge_ok(): void
    {
        $response = Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertTrue($response->payment_service_type == 'square', 'Response service type is not square');
        $this->assertEquals(5000, $response->amount, 'Transaction amount is not 5000.');
        $this->assertEquals(Constants::TRANSACTION_STATUS_PASSED, $response->status, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @return void
     */
    public function test_square_charge_wrong_nonce(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Invalid source/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'not-existent-nonce', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong CVV.
     *
     * @return void
     */
    public function test_square_charge_wrong_cvv(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Postal Code.
     *
     * @return void
     */
    public function test_square_charge_wrong_postal(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_POSTAL_CODE.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-postalcode', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with wrong Expiration date.
     *
     * @return void
     */
    public function test_square_charge_wrong_expiration_date(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge declined.
     *
     * @return void
     */
    public function test_square_charge_declined(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with already used nonce.
     *
     * @return void
     */
    public function test_square_charge_used_nonce(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);
    }

    /**
     * Charge with non-existant currency.
     *
     * @return void
     */
    public function test_square_charge_non_existant_currency(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/This merchant can only process payments in USD, but amount was provided in XXX/i');
        $this->expectExceptionCode(400);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION'), 'currency' => 'XXX']);
    }

    /**
     * Charge without location.
     *
     * @return void
     */
    public function test_square_charge_missing_location_id(): void
    {
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required field \'location_id\' is missing');
        $this->expectExceptionCode(500);

        Square::charge(['amount' => 5000, 'source_id' => 'cnon:card-nonce-ok']);
    }

    /**
     * Order creation through facade.
     *
     * @return void
     */
    public function test_square_order_make(): void
    {
        $this->data->order->discounts()->attach($this->data->discount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($this->data->tax->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($this->data->product->id, ['quantity' => 5]);

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'));

        $this->assertEquals($square->getOrder(), $this->data->order, 'Orders are not the same');
    }

    /**
     * Add product for order.
     *
     * @return void
     */
    public function test_square_order_add_product(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product, 1)->addProduct($product2, 2)->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');
    }

    /**
     * Add product and delivery fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment([
                'type'             => Constants::FULFILLMENT_TYPE_DELIVERY,
                'state'            => 'PROPOSED',
                'delivery_details' => [
                    'scheduled_type' => Constants::SCHEDULED_TYPE_ASAP,
                    'placed_at'      => now(),
                    'carrier'        => 'USPS',
                ]
            ])->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and delivery fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_fulfillment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithDeliveryDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof DeliveryDetails,
            'Fulfillment details are not DeliveryDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment([
                'type'           => Constants::FULFILLMENT_TYPE_PICKUP,
                'state'          => 'PROPOSED',
                'pickup_details' => [
                    'scheduled_type' => Constants::SCHEDULED_TYPE_ASAP,
                    'placed_at'      => now()->format(Constants::DATE_FORMAT)
                ]
            ])->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        // Make sure the fulfillment exists on the order
        $this->assertCount(1, $square->getOrder()->fulfillments, 'Fulfillment is missing from order');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithPickupDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof PickupDetails,
            'Fulfillment details are not PickupDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and pickup fulfillment with curbside pickup details for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_pickup_fulfillment_width_curbside_pickup_details(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment([
                'type'           => Constants::FULFILLMENT_TYPE_PICKUP,
                'state'          => 'PROPOSED',
                'pickup_details' => [
                    'scheduled_type'          => Constants::SCHEDULED_TYPE_ASAP,
                    'placed_at'               => now(),
                    'is_curbside_pickup'      => true,
                    'curbside_pickup_details' => [
                        'curbside_details' => 'Mazda CX5, Black, License Plate: 1234567',
                        'buyer_arrived_at' => null,
                    ]
                ]
            ])->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        // Make sure the fulfillment exists on the order
        $this->assertCount(1, $square->getOrder()->fulfillments, 'Fulfillment is missing from order');

        // Make sure the fulfillment details are PickupDetails
        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof PickupDetails,
            'Fulfillment details are not PickupDetails'
        );

        // Make sure the curbside pickup data flag is set to true
        $this->assertTrue(
            !empty($square->getOrder()->fulfillments->first()->fulfillmentDetails->is_curbside_pickup),
            'Curbside pickup flag is not set to true'
        );

        // Make sure the curbside data is present
        $this->assertNotNull(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details,
            'Curbside pickup details are not set'
        );

        $this->assertNull(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details->buyer_arrived_at,
            'Buyer arrived at is not null'
        );

        $this->assertEquals(
            'Mazda CX5, Black, License Plate: 1234567',
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->curbside_pickup_details->curbside_details,
            'Curbside details are not the same'
        );
    }

    /**
     * Add product and shipment fulfillment for order.
     *
     * @return void
     */
    public function test_square_order_add_product_and_shipment_fulfillment(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment([
                'type'             => Constants::FULFILLMENT_TYPE_SHIPMENT,
                'state'            => 'PROPOSED',
                'shipment_details' => [
                    'scheduled_type' => Constants::SCHEDULED_TYPE_ASAP,
                    'placed_at'      => now(),
                ]
            ])->setFulfillmentRecipient(TestDataHolder::buildRecipientArray())
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Add product and shipment fulfillment for order, from model.
     *
     * @return void
     */
    public function test_square_order_add_product_and_delivery_shipment_from_model(): void
    {
        $product2 = factory(Product::class)->create();

        $square = Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment($this->data->fulfillmentWithShipmentDetails)
            ->setFulfillmentRecipient($this->data->fulfillmentRecipient)
            ->save();

        $this->assertCount(2, $square->getOrder()->products, 'There is not enough products');

        $this->assertCount(1, $square->getOrder()->fulfillments, 'There is not enough fulfillments');

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails instanceof ShipmentDetails,
            'Fulfillment details are not ShipmentDetails'
        );

        $this->assertTrue(
            $square->getOrder()->fulfillments->first()->fulfillmentDetails->recipient instanceof Recipient,
            'Fulfillment details recipient is not Recipient'
        );
    }

    /**
     * Makes sure the Square Order throws an error when a fulfillment is present but no recipient is set.
     *
     * @return void
     */
    public function test_square_order_fulfillment_with_no_recipient(): void
    {
        $product2 = factory(Product::class)->create();

        // Set up the error expectations
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required fields are missing');
        $this->expectExceptionCode(500);

        Square::setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->addProduct($this->data->product, 1)
            ->addProduct($product2, 2)
            ->setFulfillment([
                'type'             => Constants::FULFILLMENT_TYPE_DELIVERY,
                'state'            => 'PROPOSED',
                'delivery_details' => [
                    'scheduled_type' => Constants::SCHEDULED_TYPE_ASAP,
                    'placed_at'      => now(),
                    'carrier'        => 'USPS',
                ]
            ])
            // ->setFulfillmentRecipient(TestDataHolder::buildRecipientArray()) // Commented out to test the error
            ->save();
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_transaction_list(): void
    {
        $array = [
            'location_id' => env('SQUARE_LOCATION'),
        ];

        $transactions = Square::payments($array);

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\Square\Models\ListPaymentsResponse', $transactions);
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_square_order_locations_list(): void
    {
        $transactions = Square::locations();

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\Square\Models\ListLocationsResponse', $transactions);
    }

    /**
     * Tests retrieving a specific location.
     *
     * @return void
     */
    public function test_square_list_catalog(): void
    {
        $catalog = Square::listCatalog();

        $this->assertNotNull($catalog);
        $this->assertIsArray($catalog);
        foreach ($catalog as $item) {
            $this->assertInstanceOf('\Square\Models\CatalogObject', $item);
        }

        $catalogItems = Square::listCatalog('ITEM');

        $this->assertNotNull($catalogItems);
        $this->assertIsArray($catalogItems);
        foreach ($catalogItems as $item) {
            $this->assertInstanceOf('\Square\Models\CatalogObject', $item);
            $this->assertEquals('ITEM', $item->getType());
        }
    }

    /**
     * Test the syncing of the product catalog for discounts.
     *
     * @return void
     */
    public function test_square_sync_discounts(): void
    {
        // Delete all discounts from the database
        Discount::truncate();
        $this->assertCount(0, Discount::all(), 'There are discount in the database after truncating');

        // Sync the products
        Square::syncDiscounts();

        // Make sure there are products
        $discounts = Discount::all();
        $this->assertGreaterThan(0, $discounts->count(), 'There are no discounts in the database');

        foreach ($discounts as $discount) {
            // Make sure every reference_type is set to square
            $this->assertNotEmpty(
                $discount->square_catalog_object_id,
                'Catalog Object ID not synced for product: ' . $discount->toJson()
            );

            // Make sure every product has a percentage or amount
            $this->assertNotNull(
                $discount->percentage || $discount->amount,
                'Discount has no percentage or amount. Discount: ' . $discount->toJson()
            );
        }
    }

    /**
     * Test the syncing of the product catalog.
     *
     * @return void
     */
    public function test_square_sync_products(): void
    {
        // Delete all products from the database
        Product::truncate();
        $this->assertCount(0, Product::all(), 'There are products in the database after truncating');

        // Sync the products
        Square::syncProducts();

        // Make sure there are products
        $products = Product::all();
        $this->assertGreaterThan(0, $products->count(), 'There are no products in the database');

        foreach ($products as $product) {
            // Make sure every reference_type is set to square
            $this->assertNotEmpty(
                $product->square_catalog_object_id,
                'Catalog Object ID not synced for product: ' . $product->toJson()
            );

            // Make sure every product has a price
            $this->assertNotNull($product->price, 'Product has no price. Product: ' . $product->toJson());

            // Make sure every product has a name
            $this->assertNotNull($product->name, 'Product has no name. Product: ' . $product->toJson());
        }
    }

    /**
     * Tests retrieving a specific location.
     *
     * @return void
     */
    public function test_square_retrieve_location(): void
    {
        $transactions = Square::retrieveLocation('main');

        $this->assertNotNull($transactions);
        $this->assertInstanceOf('\Square\Models\RetrieveLocationResponse', $transactions);
    }

    /**
     * Save an order through facade.
     *
     * @return void
     */
    public function test_square_order_facade_save(): void
    {
        $order = $this->data->order->toArray();
        $product = $this->data->product->toArray();
        $product['quantity'] = 1;
        $order['products'] = [$product];

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))->save();

        $this->assertCount(1, Order::all(), 'There is not enough orders');
        $this->assertEquals($square->getOrder()->id, Order::find(1)->id, 'Order is not the same as in charge');
        $this->assertNull($square->getOrder()->payment_service_id, 'Payment service identifier is null');
    }

    /**
     * Save and charge an order through facade.
     *
     * @return void
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_order_facade_save_and_charge(): void
    {
        $orderArr = $this->data->order->toArray();
        $product = $this->data->product->toArray();
        $product['quantity'] = 1;
        $orderArr['products'] = [$product];

        $square = Square::setOrder($orderArr, env('SQUARE_LOCATION'))->save();
        $transaction = $square->charge(
            ['amount' => $product['price'], 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
        );
        //Load order again for equals check
        $transaction->load('order');

        $this->assertCount(1, Order::all(), 'Order count is not correct');
        $this->assertCount(1, $square->getOrder()->products, 'Products count is not correct');
        $this->assertCount(1, Transaction::all(), 'There is not enough transactions');
        $this->assertNotNull($square->getOrder()->payment_service_id, 'Payment service identifier is null');
        $this->assertNotNull($transaction->order, 'Order is not connected with Transaction');
        $this->assertEquals(Order::find(1), $transaction->order, 'Order is not the same');
    }

    /**
     * Test we throw proper exception and message
     * when the customer has invalid phone number.
     *
     * @return void
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_invalid_customer_phone_number(): void
    {
        try {
            $this->data->customer->phone = 'bad phone number';
            Square::setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product)->charge(
                ['amount' => 0, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
            );
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
            $this->assertMatchesRegularExpression('/Expected phone_number to be a valid phone number/i', $e->getPrevious()->getMessage());
            $this->assertEquals(400, $e->getPrevious()->getCode());
        }
    }

    /**
     * Test we throw proper exception and message
     * when the customer has invalid email address.
     *
     * @return void
     *
     * @throws \Nikolag\Core\Exceptions\Exception
     */
    public function test_square_invalid_customer_email_address(): void
    {
        try {
            $this->data->customer->email = 'bad email address';
            Square::setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->addProduct($this->data->product)->charge(
                ['amount' => 0, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
            );
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
            $this->assertMatchesRegularExpression('/Expected email_address to be a valid email address/i', $e->getPrevious()->getMessage());
            $this->assertEquals(400, $e->getPrevious()->getCode());
        }
    }

    /**
     * Test all in one as arrays.
     *
     * @return void
     */
    public function test_square_array_all(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make'));

        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];

        $transaction = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($orderArr, env('SQUARE_LOCATION'))
            ->addProduct($productArr)
            ->charge(['amount' => 850, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
    }

    /**
     * Test all in one as models.
     *
     * @return void
     */
    public function test_square_model_all(): void
    {
        $this->data = TestDataHolder::create();
        extract($this->data->modify());

        $this->data->order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxInclusive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($product);

        $this->data->order->products->get(0)->pivot->discounts()->attach($productDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);

        $transaction = Square::setMerchant($this->data->merchant)->setCustomer($this->data->customer)->setOrder($this->data->order, env('SQUARE_LOCATION'))->charge(
            ['amount' => 850, 'source_id' => 'cnon:card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]
        );

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
    }

    /**
     * Test all in one as models.
     *
     * @return void
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function test_square_total_calculation(): void
    {
        $this->data = TestDataHolder::create();
        extract($this->data->modify());

        $this->data->order->discounts()->attach($orderDiscount->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->discounts()->attach($orderDiscountFixed->id, ['deductible_type' => Constants::DISCOUNT_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxAdditive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->taxes()->attach($taxInclusive->id, ['deductible_type' => Constants::TAX_NAMESPACE, 'featurable_type' => config('nikolag.connections.square.order.namespace'), 'scope' => Constants::DEDUCTIBLE_SCOPE_ORDER]);
        $this->data->order->products()->attach($product);

        $square = Square::setMerchant($this->data->merchant)
            ->setCustomer($this->data->customer)
            ->setOrder($this->data->order, env('SQUARE_LOCATION'))
            ->save();

        $calculatedCost = Util::calculateTotalOrderCostByModel($square->getOrder());

        $this->assertEquals(707, $calculatedCost);
    }

    /**
     * Test all in one as arrays with addition of scope.
     *
     * @return void
     */
    public function test_square_array_all_scopes(): void
    {
        extract($this->data->modify(prodFac: 'make', prodDiscFac: 'make', orderDisFac: 'make', taxAddFac: 'make'));
        $orderArr = $this->data->order->toArray();
        $orderArr['discounts'] = [$orderDiscount->toArray()];
        $productArr = $product->toArray();
        $productArr['discounts'] = [$productDiscount->toArray()];
        $productArr['taxes'] = [$taxAdditive->toArray()];

        $transaction = Square::setMerchant($this->data->merchant)->setCustomer($this->data->customer)->setOrder($orderArr, env('SQUARE_LOCATION'))->addProduct($productArr)
            ->charge([
                'amount' => 935,
                'source_id' => 'cnon:card-nonce-ok',
                'location_id' => env('SQUARE_LOCATION')
            ]);

        $transaction = $transaction->load('merchant', 'customer');

        $this->assertEquals(User::find(1), $transaction->merchant, 'Merchant is not the same as in order.');
        $this->assertEquals(Customer::find(1), $transaction->customer, 'Customer is not the same as in order.');
        $this->assertContains(Product::find(1)->id, $transaction->order->products->pluck('id'), 'Product is not part of the order.');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_PRODUCT,
            $transaction->order->discounts->where('name', $productDiscount->name)->first()->pivot->scope, 'Discount scope is not \'LINE_ITEM\'');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_PRODUCT,
            $transaction->order->taxes->where('name', $taxAdditive->name)->first()->pivot->scope, 'Tax scope is not \'LINE_ITEM\'');
        $this->assertEquals(Constants::DEDUCTIBLE_SCOPE_ORDER,
            $transaction->order->discounts->where('name', $orderDiscount->name)->first()->pivot->scope, 'Discount scope is not \'ORDER\'');
    }
}
