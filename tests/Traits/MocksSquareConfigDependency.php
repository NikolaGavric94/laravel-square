<?php

namespace Nikolag\Square\Tests\Traits;

use Nikolag\Square\SquareConfig;
use Square\Apis\WebhookSubscriptionsApi;
use Square\Http\ApiResponse;
use Square\Models\Builders\CreateWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\DeleteWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\UpdateWebhookSubscriptionResponseBuilder;
use Square\Models\Builders\UpdateWebhookSubscriptionSignatureKeyResponseBuilder;
use Square\Models\Builders\WebhookSubscriptionBuilder;
use Square\Models\Builders\ErrorBuilder;
use Square\Models\Builders\ListWebhookSubscriptionsResponseBuilder;
use Square\Models\Builders\TestWebhookSubscriptionResponseBuilder;
use Square\Models\CreateWebhookSubscriptionResponse;
use Square\Models\DeleteWebhookSubscriptionResponse;
use Square\Models\ListWebhookSubscriptionsResponse;
use Square\Models\UpdateWebhookSubscriptionResponse;
use Square\Models\UpdateWebhookSubscriptionSignatureKeyResponse;
use Square\Models\TestWebhookSubscriptionResponse;
use Square\Models\WebhookSubscription;

/**
 * Square API mocking trait based on dependency injection pattern.
 *
 */
trait MocksSquareConfigDependency
{
    /**
     * Persistent mock instances to avoid service container conflicts.
     */
    private $mockWebhooksApi;
    private $mockSquareConfig;

    /**
     * Mock the SquareConfig dependency for webhook operations.
     *
     * @param string  $endpoint     The webhook endpoint to mock.
     * @param array|null   $responseData The data to include in successful responses.
     * @param boolean $shouldFail   Whether to simulate an API error.
     * @param string  $errorMessage Error message if shouldFail is true.
     * @param int     $errorCode    HTTP error code if shouldFail is true.
     */
    protected function mockSquareWebhookEndpoint(
        string $endpoint,
        ?array $responseData = null,
        bool $shouldFail = false,
        string $errorMessage = 'API Error',
        int $errorCode = 400
    ): void {
        if ($shouldFail) {
            $this->mockSquareWebhookError($endpoint, $errorMessage, $errorCode);
        } else {
            $this->mockSquareWebhookSuccess($endpoint, $responseData);
        }
    }

    /**
     * Mock a successful webhook API response.
     *
     * @param string $endpoint The webhook endpoint to mock.
     * @param array|null  $responseData The data to include in the response.
     *
     * @return void
     */
    private function mockSquareWebhookSuccess(string $endpoint, ?array $responseData = null): void
    {
        // Build the appropriate response based on endpoint
        $mockResult = $this->buildWebhookResponseForEndpoint($endpoint, $responseData);

        // Create mock API response
        $mockApiResponse = $this->createMock(ApiResponse::class);
        $mockApiResponse->method('isError')->willReturn(false);
        $mockApiResponse->method('isSuccess')->willReturn(true);
        $mockApiResponse->method('getResult')->willReturn($mockResult);
        $mockApiResponse->method('getErrors')->willReturn([]);

        $this->bindMockToServiceContainer($endpoint, $mockApiResponse);
    }

    /**
     * Mock an error webhook API response.
     *
     * @param string $endpoint The webhook endpoint to mock.
     * @param string $errorMessage The error message to return.
     * @param int    $errorCode The HTTP status code to return.
     *
     * @return void
     */
    private function mockSquareWebhookError(string $endpoint, string $errorMessage, int $errorCode): void
    {
        // Create error object using Square's builder
        $error = ErrorBuilder::init('INVALID_REQUEST_ERROR', 'GENERIC_ERROR')
            ->detail($errorMessage)
            ->field(null)
            ->build();

        // Create mock API response for error
        $mockApiResponse = $this->createMock(ApiResponse::class);
        $mockApiResponse->method('isError')->willReturn(true);
        $mockApiResponse->method('isSuccess')->willReturn(false);
        $mockApiResponse->method('getResult')->willReturn(null);
        $mockApiResponse->method('getErrors')->willReturn([$error]);
        $mockApiResponse->method('getStatusCode')->willReturn($errorCode);

        $this->bindMockToServiceContainer($endpoint, $mockApiResponse);
    }

    /**
     * Build the appropriate response object based on the endpoint.
     */
    private function buildWebhookResponseForEndpoint(string $endpoint, ?array $data): mixed
    {
        switch ($endpoint) {
            case 'createWebhookSubscription':
                return $this->buildCreateWebhookResponse($data);

            case 'deleteWebhookSubscription':
                return $this->buildDeleteWebhookResponse();

            case 'listWebhookSubscriptions':
                // For simplicity, returning an array of one subscription
                return $this->buildListWebhookResponse($data);

            case 'updateWebhookSubscription':
                return $this->buildUpdateWebhookResponse($data);

            case 'retrieveWebhookSubscription':
                return $this->buildRetrieveWebhookResponse($data);

            case 'testWebhookSubscription':
                return $this->buildTestWebhookResponse($data);

            case 'updateWebhookSubscriptionSignatureKey':
                return $this->buildUpdateWebhookSignatureKeyResponse($data);

            default:
                return $this->buildCreateWebhookResponse($data);
        }
    }

    /**
     * Build a create webhook subscription response.
     *
     * @param array $data The data to include in the response.
     *
     * @return CreateWebhookSubscriptionResponse
     */
    private function buildCreateWebhookResponse(array $data): CreateWebhookSubscriptionResponse
    {
        $subscription = $this->buildSingleWebhook($data);

        return CreateWebhookSubscriptionResponseBuilder::init()
            ->subscription($subscription)
            ->build();
    }

    /**
     * Build a list webhook subscriptions response.
     *
     * @param null|array $data The data to include in the response.
     *
     * @return ListWebhookSubscriptionsResponse
     */
    private function buildListWebhookResponse(?array $data = null): ListWebhookSubscriptionsResponse
    {
        if ($data !== null) {
            $response = collect($data)->map(function ($item) {
                return $this->buildSingleWebhook($item);
            })->toArray();
        } else {
            $response = null;
        }

        return ListWebhookSubscriptionsResponseBuilder::init()
            ->subscriptions($response)
            ->build();
    }

    /**
     * Build a retrieve webhook subscription response.
     *
     * @param array $data The data to include in the response.
     *
     * @return CreateWebhookSubscriptionResponse
     */
    private function buildRetrieveWebhookResponse(array $data): CreateWebhookSubscriptionResponse
    {
        $subscription = $this->buildSingleWebhook($data);

        return CreateWebhookSubscriptionResponseBuilder::init()
            ->subscription($subscription)
            ->build();
    }

    /**
     * Build an update webhook subscription response.
     *
     * @param array $data The data to include in the response.
     *
     * @return UpdateWebhookSubscriptionResponse
     */
    private function buildUpdateWebhookResponse(array $data): UpdateWebhookSubscriptionResponse
    {
        $subscription = $this->buildSingleWebhook($data);

        return UpdateWebhookSubscriptionResponseBuilder::init()
            ->subscription($subscription)
            ->build();
    }

    /**
     * Build a delete webhook subscription response.
     *
     * @return DeleteWebhookSubscriptionResponse
     */
    private function buildDeleteWebhookResponse(): DeleteWebhookSubscriptionResponse
    {
        return DeleteWebhookSubscriptionResponseBuilder::init()->build();
    }

    /**
     * Build a test webhook subscription response.
     *
     * @param array|null $data The data to include in the response.
     *
     * @return TestWebhookSubscriptionResponse
     */
    private function buildTestWebhookResponse(?array $data = null): TestWebhookSubscriptionResponse
    {
        // For now, create a basic response - the actual properties will depend on Square SDK
        return TestWebhookSubscriptionResponseBuilder::init()->build();
    }

    /**
     * Build an update webhook subscription signature key response.
     *
     * @param array|null $data The data to include in the response.
     *
     * @return UpdateWebhookSubscriptionSignatureKeyResponse
     */
    private function buildUpdateWebhookSignatureKeyResponse(?array $data = null): UpdateWebhookSubscriptionSignatureKeyResponse
    {
        $responseBuilder = UpdateWebhookSubscriptionSignatureKeyResponseBuilder::init();

        if ($data !== null && isset($data['signatureKey'])) {
            $responseBuilder->signatureKey($data['signatureKey']);
        } else {
            // Generate a mock signature key if none provided
            $responseBuilder->signatureKey('test_signature_key_' . uniqid());
        }

        return $responseBuilder->build();
    }

    /**
     * Bind the mock to the service container using dependency injection.
     *
     * @param string      $endpoint        The webhook endpoint to mock.
     * @param ApiResponse $mockApiResponse The mock API response to return.
     *
     * @return void
     */
    private function bindMockToServiceContainer(string $endpoint, ApiResponse $mockApiResponse): void
    {
        // Get or create a persistent mock webhooks API
        if (!isset($this->mockWebhooksApi)) {
            $this->mockWebhooksApi = $this->createMock(WebhookSubscriptionsApi::class);
        }

        // Configure the specific endpoint on the existing mock
        $this->mockWebhooksApi->method($endpoint)->willReturn($mockApiResponse);

        // Get or create a persistent mock SquareConfig
        if (!isset($this->mockSquareConfig)) {
            $this->mockSquareConfig = $this->createMock(SquareConfig::class);
            $this->mockSquareConfig->method('webhooksAPI')->willReturn($this->mockWebhooksApi);

            // Bind once to the service container
            $this->app->instance(SquareConfig::class, $this->mockSquareConfig);
        }
    }

    /**
     * Mocks the webhooksAPI()->createWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockCreateWebhookSuccess(array $responseData = []): void
    {
        $this->mockSquareWebhookEndpoint('createWebhookSubscription', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->createWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockCreateWebhookError(string $message = 'Create webhook failed', int $code = 400): void
    {
        $this->mockSquareWebhookEndpoint('createWebhookSubscription', [], true, $message, $code);
    }

    /**
     * Mocks the webhooksAPI()->listWebhookSubscriptions(...) method in the SquareService class.
     *
     * @param null|array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockListWebhookSuccess(?array $responseData = null): void
    {
        $this->mockSquareWebhookEndpoint('listWebhookSubscriptions', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->retrieveWebhookSubscription($subscriptionId) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockRetrieveWebhookSuccess(array $responseData = []): void
    {
        $this->mockSquareWebhookEndpoint('retrieveWebhookSubscription', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->updateWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockUpdateWebhookSuccess(array $responseData = []): void
    {
        $this->mockSquareWebhookEndpoint('updateWebhookSubscription', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->updateWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockUpdateWebhookError(string $message = 'Update webhook failed', int $code = 400): void
    {
        $this->mockSquareWebhookEndpoint('updateWebhookSubscription', [], true, $message, $code);
    }

    /**
     * Mocks the webhooksAPI()->deleteWebhookSubscription($subscriptionId) method in the SquareService class.
     *
     * @return void
     */
    protected function mockDeleteWebhookSuccess(): void
    {
        $this->mockSquareWebhookEndpoint('deleteWebhookSubscription');
    }

    /**
     * Mocks the webhooksAPI()->deleteWebhookSubscription($subscriptionId) method in the SquareService class.
     *
     * @return void
     */
    protected function mockDeleteWebhookError(string $message = 'Delete webhook failed', int $code = 404): void
    {
        $this->mockSquareWebhookEndpoint('deleteWebhookSubscription', [], true, $message, $code);
    }

    /**
     * Mocks the webhooksAPI()->testWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockTestWebhookSuccess(array $responseData = []): void
    {
        $this->mockSquareWebhookEndpoint('testWebhookSubscription', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->testWebhookSubscription($subscriptionId, $request) method in the SquareService class.
     *
     * @param string $message Error message to return.
     * @param int $code HTTP error code to return.
     *
     * @return void
     */
    protected function mockTestWebhookError(string $message = 'Test webhook failed', int $code = 400): void
    {
        $this->mockSquareWebhookEndpoint('testWebhookSubscription', [], true, $message, $code);
    }

    /**
     * Mocks the webhooksAPI()->updateWebhookSubscriptionSignatureKey($subscriptionId, $request) method in the SquareService class.
     *
     * @param array $responseData Data to include in the successful response.
     *
     * @return void
     */
    protected function mockUpdateWebhookSignatureKey(array $responseData = []): void
    {
        $this->mockSquareWebhookEndpoint('updateWebhookSubscriptionSignatureKey', $responseData);
    }

    /**
     * Mocks the webhooksAPI()->updateWebhookSubscriptionSignatureKey($subscriptionId, $request) method in the SquareService class.
     *
     * @param string $message Error message to return.
     * @param int $code HTTP error code to return.
     *
     * @return void
     */
    protected function mockUpdateWebhookSignatureKeyError(string $message = 'Update webhook signature key failed', int $code = 400): void
    {
        $this->mockSquareWebhookEndpoint('updateWebhookSubscriptionSignatureKey', [], true, $message, $code);
    }

    /**
     * Build a single webhook subscription model.
     *
     * @param array $data The data to include in the subscription.
     *
     * @return WebhookSubscription
     */
    private function buildSingleWebhook(array $data): WebhookSubscription
    {
        return WebhookSubscriptionBuilder::init()
            ->id($data['id'])
            ->name($data['name'])
            ->enabled($data['enabled'])
            ->eventTypes($data['eventTypes'])
            ->notificationUrl($data['notificationUrl'])
            ->apiVersion($data['apiVersion'])
            ->signatureKey($data['signatureKey'])
            ->build();
    }
}
