<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Builders\SquareRequestBuilder;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareService;
use Square\Models\BatchDeleteCatalogObjectsResponse;
use Square\Models\CreateCatalogImageResponse;
use Square\Models\BatchUpsertCatalogObjectsResponse;
use Square\Models\ListLocationsResponse;
use Square\Models\ListPaymentsResponse;
use Square\Models\RetrieveLocationResponse;

/**
 * @method static SquareService save()
 * @method static SquareService saveToSquare()
 * @method static string getCurrency()
 * @method static BatchDeleteCatalogObjectsResponse batchDeleteCatalogObjects(array $catalogObjectIds)
 * @method static CreateCatalogImageResponse createCatalogImage(
 *     CreateCatalogImageRequest $createCatalogImageRequest,
 *     string $filePath
 * )
 * @method static BatchUpsertCatalogObjectsResponse batchUpsertCatalog(
 *     BatchUpsertCatalogObjectsRequest $batchUpsertCatalogRequest
 * )
 * @method static ListLocationsResponse locations()
 * @method static RetrieveLocationResponse retrieveLocation(string $locationId)
 * @method static SquareRequestBuilder getSquareBuilder()
 * @method static array listCatalog(?string $types)
 * @method static void syncDiscounts()
 * @method static void syncLocations()
 * @method static void syncProducts()
 * @method static void syncModifiers()
 * @method static void syncTaxes()
 * @method static Transaction charge(array $data)
 * @method static ListPaymentsResponse payments(array $options)
 * @method static mixed getCustomer()
 * @method static SquareServiceContract setCustomer($customer)
 * @method static mixed getMerchant()
 * @method static SquareServiceContract setMerchant($merchant)
 * @method static mixed getOrder()
 * @method static SquareServiceContract addProduct($product, int $quantity, string $currency = 'USD')
 * @method static SquareServiceContract setOrder($order, string $locationId, string $currency = 'USD')
 *
 * @see \Nikolag\Square\SquareService
 */
class Square extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SquareService::class;
    }
}
