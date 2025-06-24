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
 * @method static SquareServiceContract setCustomer($customer)
 * @method static mixed getMerchant()
 * @method static SquareServiceContract setMerchant($merchant)
 * @method static mixed getOrder()
 * @method static SquareServiceContract addProduct($product, int $quantity, string $currency = 'USD')
 * @method static SquareServiceContract setOrder($order, string $locationId, string $currency = 'USD')
 * 
 * Webhook Management Methods
 * @method static WebhookSubscription createWebhook(WebhookBuilder $builder)
 * @method static WebhookSubscription updateWebhook(string $subscriptionId, WebhookBuilder $builder)
 * @method static bool deleteWebhook(string $subscriptionId)
 * @method static bool markWebhookEventProcessed(string $eventId)
 * @method static bool markWebhookEventFailed(string $eventId)
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
