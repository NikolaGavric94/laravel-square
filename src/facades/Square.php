<?php

namespace Nikolag\Square\Facades;

use Illuminate\Support\Facades\Facade;
use Nikolag\Square\Builders\WebhookBuilder;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Models\WebhookEvent;
use Nikolag\Square\Models\WebhookSubscription;
use Nikolag\Square\SquareService;
use Square\Models\ListLocationsResponse;
use Square\Models\ListPaymentsResponse;
use Square\Models\ListWebhookEventTypesResponse;
use Square\Models\ListWebhookSubscriptionsResponse;
use Square\Models\TestWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionSignatureKeyResponse;

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
