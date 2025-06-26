# Square Webhooks Documentation

This documentation covers the webhook functionality added to the Laravel Square package, enabling you to receive real-time notifications from Square for order events.

## Table of Contents

1. [Creating Webhooks](#creating-webhooks)
2. [Managing Webhooks](#managing-webhooks)
3. [Route Handler](#route-handler)
4. [Processing Webhook Events](#processing-webhook-events)
5. [Security Considerations](#security-considerations)

## Creating Webhooks

### Basic Webhook Creation

```php
use Nikolag\Square\Facades\Square;

// Create webhook builder
$builder = Square::webhookBuilder()
    ->name('Order Webhook')
    ->notificationUrl('https://example.com/webhooks/square')
    ->eventTypes(['order.created', 'order.updated'])
    ->enabled();

// Create the webhook subscription
$subscription = Square::createWebhookSubscription($builder);
```

## Managing Webhooks

### Listing Webhooks

```php
use Nikolag\Square\Facades\Square;

// List all webhooks
$webhooks = Square::listWebhookSubscriptions();

// OR

// List with pagination
$webhooks = Square::listWebhookSubscriptions(
    cursor: null,
    includeDisabled: false,
    sortOrder: 'ASC',
    limit: 10
);
```

### Updating Webhooks

```php
use Nikolag\Square\Facades\Square;

$subscriptionId = 'your-webhook-subscription-id';

$builder = Square::webhookBuilder()
    ->name('Updated Webhook Name')
    ->addEventType('order.fulfillment.updated')
    ->enabled();

$updatedWebhook = Square::updateWebhookSubscription($subscriptionId, $builder);
```

### Deleting Webhooks

```php
use Nikolag\Square\Facades\Square;

$subscriptionId = 'your-webhook-subscription-id';
$success = Square::deleteWebhookSubscription($subscriptionId);

if ($success) {
    echo "Webhook deleted successfully";
}
```

### Testing Webhooks

```php
use Nikolag\Square\Facades\Square;

$subscriptionId = 'your-webhook-subscription-id';
// This will generate a POST Request to the webhook's specified Notification URL
$testResult = Square::testWebhookSubscription($subscriptionId);
```

### Updating Signature Keys

```php
$subscriptionId = 'your-webhook-subscription-id';
$result = Square::updateWebhookSignatureKey($subscriptionId);

echo "New signature key: " . $result->getSignatureKey();
```

## Route Handler

You will need a way to process the webhook event data sent to your application.  This Square package **does not**
include route handlers as you will need to create these in your own application. Here is a sample approach:

```php
// routes/web.php or routes/api.php
Route::post('/webhooks/square', [WebhookController::class, 'handle']);
```

```php
// app/Http/Controllers/WebhookController.php
<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nikolag\Square\Facades\Square;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            $event = Square::processWebhook($request);

            // Logic to process the event model that was created goes here
            // For example, you can dispatch a job to process the newly updated order data, or send an email
            // notifying a user that a new payment was processes, etc.

            return response('OK', 200);

        } catch (Exception $e) {
            Log::error('Square webhook error: ' . $e->getMessage());
            return response('Error', 500);
        }
    }
}
```

## Processing Webhook Events

After the event has been processed and a `WebhookEvent` Model has been created in your application, you can now take
action on the newly registered event.  For example:

### Retrieving and Processing Events

```php

use Exception;
use Nikolag\Square\Models\WebhookEvent;

// Get pending events for processing
$pendingEvents = WebhookEvent::where('status', 'pending')->get();

foreach ($pendingEvents as $event) {
    try {
        $this->processOrderEvent($event); // Custom logic to process your order
        $event->markAsProcessed();
    } catch (Exception $e) {
        $event->markAsFailed($e->getMessage());
    }
}
```

### Webhook Retry Handling

Square automatically retries failed webhook deliveries with exponential backoff. The package captures retry information:

```php
// When processing a retry event
if ($event->isRetry()) {
    Log::info('Processing webhook retry', [
        'event_id' => $event->square_event_id,
        'retry_number' => $event->retry_number,
        'retry_reason' => $event->retry_reason,
        'initial_delivery' => $event->initial_delivery_timestamp,
        'current_attempt' => now()
    ]);

    // Handle retry-specific logic
    if ($event->retry_number > 3) {
        // High retry count - may need special handling
        $this->handleHighRetryCount($event);
    }
}

// Query retry events
$retryEvents = WebhookEvent::retries()->get();
$originalEvents = WebhookEvent::original()->get();

// Find related retry attempts
$originalEvent = WebhookEvent::where('square_event_id', $event->square_event_id)
    ->where('retry_number', null)
    ->first();
```

## Security Considerations

### Signature Verification

The package automatically verifies webhook signatures to ensure authenticity:

```php
// Signature verification is handled automatically in processWebhook()
try {
    $event = Square::processWebhook($headers, $payload);
    // Event is verified and safe to process
} catch (InvalidSquareSignatureException $e) {
    // Invalid signature - potential security issue
    Log::warning('Invalid webhook signature received');
    return response('Unauthorized', 401);
}
```

### Best Practices

1. **Always verify signatures** - Never skip signature verification
2. **Use HTTPS** - Webhook URLs must use HTTPS
3. **Validate payload structure** - Check event data before processing
4. **Implement idempotency** - Handle duplicate events gracefully
5. **Rate limiting** - Implement rate limiting on webhook endpoints
6. **Logging** - Log all webhook activities for monitoring
