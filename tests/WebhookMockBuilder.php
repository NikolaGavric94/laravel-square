<?php

namespace Nikolag\Square\Tests;

/**
 * Fluent builder for webhook mocks.
 *
 * This class provides a fluent interface for configuring webhook endpoint mocks.
 * It works with the MocksSquareApi trait to provide a clean, readable API for
 * setting up both success and error responses for different webhook endpoints.
 */
class WebhookMockBuilder
{
    private $testCase;
    private string $endpoint;
    private array $successData = [];
    private ?string $errorMessage = null;
    private int $errorCode = 400;

    public function __construct($testCase, string $endpoint)
    {
        $this->testCase = $testCase;
        $this->endpoint = $endpoint;
    }

    /**
     * Configure success response data.
     *
     * @param  array  $data  Data to include in the success response
     * @return $this
     */
    public function withSuccess(array $data = []): self
    {
        $this->successData = $data;
        $this->errorMessage = null;

        return $this;
    }

    /**
     * Configure error response.
     *
     * @param  string  $message  Error message
     * @param  int  $code  HTTP status code
     * @return $this
     */
    public function withError(string $message, int $code = 400): self
    {
        $this->errorMessage = $message;
        $this->errorCode = $code;
        $this->successData = [];

        return $this;
    }

    /**
     * Apply the mock configuration to the test case.
     *
     * This method calls the mockWebhookEndpoint method on the test case
     * to actually configure the mocks based on the builder's state.
     *
     * @return void
     */
    public function apply(): void
    {
        if ($this->errorMessage !== null) {
            $this->testCase->mockWebhookEndpoint(
                $this->endpoint,
                ['message' => $this->errorMessage, 'code' => $this->errorCode],
                true
            );
        } else {
            $this->testCase->mockWebhookEndpoint($this->endpoint, $this->successData, false);
        }
    }

    /**
     * Get the endpoint being mocked.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the success data configured for this mock.
     *
     * @return array
     */
    public function getSuccessData(): array
    {
        return $this->successData;
    }

    /**
     * Check if this mock is configured for an error response.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->errorMessage !== null;
    }
}
