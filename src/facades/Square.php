<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Nikolag\Square\SquareService save()
 * @method static \Nikolag\Square\Models\Transaction charge(array $options)
 * @method static \SquareConnect\Model\ListPaymentsResponse payments(array $options)
 * @method static mixed getCustomer()
 * @method static void setCustomer($customer)
 * @method static mixed getMerchant()
 * @method static \Nikolag\Square\Contracts\SquareServiceContract setMerchant($merchant)
 * @method static mixed getOrder()
 * @method static \Nikolag\Square\Contracts\SquareServiceContract addProduct($product, int $quantity, string $currency = 'USD')
 * @method static \Nikolag\Square\Contracts\SquareServiceContract setOrder($order, string $locationId, string $currency = 'USD')
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
