<?php

namespace Nikolag\Square\Tests\Traits;

use Nikolag\Square\SquareConfig;
use Nikolag\Square\Tests\WebhookMockBuilder;
use Square\Apis\WebhookSubscriptionsApi;
use Square\Http\ApiResponse;
use Square\Models\Builders\CreateWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\DeleteWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\UpdateWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\ErrorBuilder;
use Square\Models\Builders\WebhookSubscriptionBuilder;
use Square\Models\CreateWebhookSubscriptionResponse;
use Square\Models\DeleteWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionResponse;

/**
 * Trait for mocking Square API responses in tests.
 *
 * This trait provides reusable methods for mocking different Square API endpoints
 * using Square's official SDK builders. It supports both success and error responses
 * and can be easily extended for additional endpoints.
 */
trait MocksSquareApi
{
    /**
     * Mock a successful webhook response using Square's builders.
     * Backward compatible method for existing tests.
     *
     * @param array $data Custom data to override defaults
     *
     * @return void
     */
    protected function mockWebhookSuccess(array $data = []): void
    {
        $this->mockWebhookEndpoint('createWebhookSubscription', $data, false);
    }

    /**
     * Mock an error webhook response using Square's builders.
     * Backward compatible method for existing tests.
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     *
     * @return void
     */
    protected function mockWebhookError(string $message = 'API Error', int $code = 400): void
    {
        $this->mockWebhookEndpoint('createWebhookSubscription', ['message' => $message, 'code' => $code], true);
    }

    /**
     * Internal method for mocking webhook success.
     * Public method for WebhookMockBuilder access.
     *
     * @param array $data
     *
     * @return void
     */
    public function mockWebhookSuccessInternal(array $data = []): void
    {
        $this->mockWebhookEndpoint('createWebhookSubscription', $data, false);
    }

    /**
     * Internal method for mocking webhook errors.
     * Public method for WebhookMockBuilder access.
     *
     * @param string $message
     * @param int $code
     *
     * @return void
     */
    public function mockWebhookErrorInternal(string $message = 'API Error', int $code = 400): void
    {
        $this->mockWebhookEndpoint('createWebhookSubscription', ['message' => $message, 'code' => $code], true);
    }

    /**
     * Mock a specific webhook endpoint with success or error response.
     *
     * @param string $endpoint The endpoint to mock ('createWebhookSubscription', 'deleteWebhookSubscription', etc.)
     * @param array $data Data for success response or error info for error response
     * @param bool $isError Whether this is an error response
     * @param int $statusCode HTTP status code
     *
     * @return void
     */
    public function mockWebhookEndpoint(string $endpoint, array $data = [], bool $isError = false, ?int $statusCode = null): void
    {
        $statusCode = $statusCode ?? ($isError ? ($data['code'] ?? 400) : 200);

        if ($isError) {
            $error = ErrorBuilder::init('INVALID_REQUEST_ERROR', $statusCode === 400 ? 'GENERIC_ERROR' : 'HTTP_ERROR_' . $statusCode)
                ->detail($data['message'] ?? 'API Error')
                ->field(null)
                ->build();

            $result = [$error];
        } else {
            $result = $this->buildSuccessResponse($endpoint, $data);
        }

        $this->mockSquareApiResponse($endpoint, $result, $isError, $statusCode);
    }

    /**
     * Build success response based on endpoint type.
     *
     * @param string $endpoint
     * @param array $data
     *
     * @return mixed
     */
    private function buildSuccessResponse(string $endpoint, array $data)
    {
        switch ($endpoint) {
            case 'createWebhookSubscription':
                return $this->buildCreateWebhookResponse($data);

            case 'deleteWebhookSubscription':
                return $this->buildDeleteWebhookResponse($data);

            default:
                // Default to create webhook response for backward compatibility
                return $this->buildCreateWebhookResponse($data);
        }
    }

    /**
     * Build create webhook subscription response.
     *
     * @param array $data
     *
     * @return CreateWebhookSubscriptionResponse
     */
    private function buildCreateWebhookResponse(array $data): CreateWebhookSubscriptionResponse
    {
        // Set up default values if not provided
        $defaultData = [
            'id' => 'wh_default_123',
            'name' => 'Default Webhook',
            'notificationUrl' => 'https://example.com/webhook',
            'eventTypes' => ['order.created'],
            'apiVersion' => '2023-10-11',
            'signatureKey' => 'default_signature_key',
            'enabled' => true
        ];

        $mergedData = array_merge($defaultData, $data);

        // Build the webhook subscription using Square's builder
        $subscription = WebhookSubscriptionBuilder::init()
            ->id($mergedData['id'])
            ->name($mergedData['name'])
            ->enabled($mergedData['enabled'])
            ->eventTypes($mergedData['eventTypes'])
            ->notificationUrl($mergedData['notificationUrl'])
            ->apiVersion($mergedData['apiVersion'])
            ->signatureKey($mergedData['signatureKey'])
            ->build();

        // Build the complete response using Square's builder
        return CreateWebhookSubscriptionResponseBuilder::init()
            ->subscription($subscription)
            ->build();
    }

    /**
     * Build delete webhook subscription response.
     *
     * @param array $data
     *
     * @return DeleteWebhookSubscriptionResponse
     */
    private function buildDeleteWebhookResponse(array $data): DeleteWebhookSubscriptionResponse
    {
        // Delete response is typically empty for success
        // $data parameter kept for consistency but unused for delete responses
        return DeleteWebhookSubscriptionResponseBuilder::init()->build();
    }

    /**
     * Mock a Square API response for webhook endpoints.
     *
     * @param string $endpoint The endpoint being mocked
     * @param mixed $result The result data or error array
     * @param bool $isError Whether this is an error response
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function mockSquareApiResponse(string $endpoint, $result, bool $isError = false, int $statusCode = 200): void
    {
        // Create and configure mocks
        $mockResponse = $this->createMock(ApiResponse::class);
        $mockResponse->method('isError')->willReturn($isError);
        $mockResponse->method('getStatusCode')->willReturn($statusCode);

        if ($isError) {
            $mockResponse->method('getErrors')->willReturn($result);
        } else {
            $mockResponse->method('getResult')->willReturn($result);
        }

        $mockApi = $this->createMock(WebhookSubscriptionsApi::class);

        // Map endpoint to API method
        $methodMap = [
            'createWebhookSubscription' => 'createWebhookSubscription',
            'deleteWebhookSubscription' => 'deleteWebhookSubscription'
        ];

        $apiMethod = $methodMap[$endpoint] ?? 'createWebhookSubscription';
        $mockApi->method($apiMethod)->willReturn($mockResponse);

        $mockConfig = $this->createMock(SquareConfig::class);
        $mockConfig->method('webhooksAPI')->willReturn($mockApi);

        $this->app->instance(SquareConfig::class, $mockConfig);
    }

    /**
     * Mock webhook endpoint with fluent API.
     *
     * Example usage:
     * $this->mockWebhook('createWebhookSubscription')
     *      ->withSuccess(['id' => 'wh_123', 'name' => 'Test'])
     *      ->apply();
     *
     * $this->mockWebhook('deleteWebhookSubscription')
     *      ->withSuccess()
     *      ->apply();
     *
     * @param string $endpoint
     * @return WebhookMockBuilder
     */
    protected function mockWebhook(string $endpoint): WebhookMockBuilder
    {
        return new WebhookMockBuilder($this, $endpoint);
    }
}

