<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\SquareService;
use Square\Models\ListPaymentsResponse;

/**
 * @method static SquareService save()
 * @method static Transaction charge(array $data)
 * @method static ListPaymentsResponse payments(array $options)
 * @method static mixed getCustomer()
 * @method static void setCustomer($customer)
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
    protected static function getFacadeAccessor()
    {
        return 'square';
    }
}
