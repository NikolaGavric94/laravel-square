<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareService;
use Square\Models\ListLocationsResponse;
use Square\Models\ListPaymentsResponse;

/**
 * @method static SquareService save()
 * @method static Transaction charge(array $options)
 * @method static ListPaymentsResponse payments(array $options)
 * @method static ListLocationsResponse locations()
 * @method static array listCatalog(array $typesFilter = [])
 * @method static mixed getCustomer()
 * @method static SquareService setCustomer(mixed $customer)
 * @method static mixed getMerchant()
 * @method static SquareService setMerchant(mixed $merchant)
 * @method static mixed getOrder()
 * @method static SquareService addProduct(mixed $product, int $quantity = 1, string $currency = 'USD')
 * @method static SquareService setOrder(mixed $order, string $locationId, string $currency = 'USD')
 * @method static SquareService setFulfillment(mixed $fulfillment)
 * @method static SquareService setFulfillmentRecipient(mixed $recipient)
 * @method static mixed getFulfillment()
 * @method static mixed getFulfillmentDetails()
 * @method static mixed getFulfillmentRecipient()
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
