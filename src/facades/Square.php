<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\SquareService;
use Square\Models\ListPaymentsResponse;
use Square\Models\ListWebhookEventTypesResponse;
use Square\Models\ListWebhookSubscriptionsResponse;
use Square\Models\TestWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionSignatureKeyResponse;

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
 * @method static WebhookSubscription createWebhookSubscription(WebhookBuilder $builder)
 * @method static WebhookSubscription updateWebhookSubscription(string $subscriptionId, WebhookBuilder $builder)
 * @method static bool deleteWebhookSubscription(string $subscriptionId)
 * @method static ListWebhookSubscriptionsResponse listWebhookSubscriptions(string $cursor = null, bool $includeDisabled = false, string $sortOrder = null, int $limit = null)
 * @method static ListWebhookEventTypesResponse listWebhookEventTypes(string $apiVersion = null)
 * @method static TestWebhookSubscriptionResponse testWebhookSubscription(string $subscriptionId, array $eventData = null)
 * @method static UpdateWebhookSubscriptionSignatureKeyResponse updateWebhookSignatureKey(string $subscriptionId)
 * @method static WebhookEvent processWebhook(Request $request)
 * @method static WebhookBuilder webhookBuilder()
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
