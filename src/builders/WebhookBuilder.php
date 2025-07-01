<?php

namespace Nikolag\Square\Builders;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Square\ConfigurationDefaults;
use Square\Models\CreateWebhookSubscriptionRequest;
use Square\Models\UpdateWebhookSubscriptionRequest;
use Square\Models\WebhookSubscription;

class WebhookBuilder
{
    /**
     * @var string|null
     */
    private ?string $name = null;

    /**
     * @var string|null
     */
    private ?string $notificationUrl = null;

    /**
     * @var array
     */
    private array $eventTypes = [];

    /**
     * @var string
     */
    private string $apiVersion = ConfigurationDefaults::SQUARE_VERSION;

    /**
     * @var bool
     */
    private bool $enabled = true;

    /**
     * Set the webhook name.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the notification URL.
     *
     * @param string $url
     * @return $this
     */
    public function notificationUrl(string $url): self
    {
        $this->notificationUrl = $url;
        return $this;
    }

    /**
     * Set the event types to subscribe to.
     *
     * @param array $eventTypes
     * @return $this
     */
    public function eventTypes(array $eventTypes): self
    {
        $this->eventTypes = $eventTypes;
        return $this;
    }

    /**
     * Add a single event type to subscribe to.
     *
     * @param string $eventType
     * @return $this
     */
    public function addEventType(string $eventType): self
    {
        if (!in_array($eventType, $this->eventTypes)) {
            $this->eventTypes[] = $eventType;
        }
        return $this;
    }

    /**
     * Set the API version.
     *
     * @param string $version
     * @return $this
     */
    public function apiVersion(string $version): self
    {
        $this->apiVersion = $version;
        return $this;
    }

    /**
     * Set whether the webhook is enabled.
     *
     * @param bool $enabled
     * @return $this
     */
    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Disable the webhook.
     *
     * @return $this
     */
    public function disabled(): self
    {
        return $this->enabled(false);
    }

    /**
     * Build a CreateWebhookSubscriptionRequest.
     *
     * @return CreateWebhookSubscriptionRequest
     * @throws MissingPropertyException
     */
    public function buildCreateRequest(): CreateWebhookSubscriptionRequest
    {
        $this->validateRequiredFields();

        $subscription = new WebhookSubscription();
        $subscription->setName($this->name);
        $subscription->setNotificationUrl($this->notificationUrl);
        $subscription->setEventTypes($this->eventTypes);
        $subscription->setApiVersion($this->apiVersion);
        $subscription->setEnabled($this->enabled);

        $request = new CreateWebhookSubscriptionRequest($subscription);

        return $request;
    }

    /**
     * Build an UpdateWebhookSubscriptionRequest.
     *
     * @return UpdateWebhookSubscriptionRequest
     * @throws MissingPropertyException
     */
    public function buildUpdateRequest(): UpdateWebhookSubscriptionRequest
    {
        $subscription = new WebhookSubscription();

        if ($this->name !== null) {
            $subscription->setName($this->name);
        }

        if ($this->notificationUrl !== null) {
            $subscription->setNotificationUrl($this->notificationUrl);
        }

        if (!empty($this->eventTypes)) {
            $subscription->setEventTypes($this->eventTypes);
        }

        $subscription->setApiVersion($this->apiVersion);
        $subscription->setEnabled($this->enabled);

        $request = new UpdateWebhookSubscriptionRequest();
        $request->setSubscription($subscription);

        return $request;
    }

    /**
     * Reset the builder to its initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->name = null;
        $this->notificationUrl = null;
        $this->eventTypes = [];
        $this->apiVersion = ConfigurationDefaults::SQUARE_VERSION;
        $this->enabled = true;

        return $this;
    }

    /**
     * Validate that required fields are set.
     *
     * @throws MissingPropertyException
     */
    private function validateRequiredFields(): void
    {
        if (empty($this->name)) {
            throw new MissingPropertyException('Webhook name is required');
        }

        if (empty($this->notificationUrl)) {
            throw new MissingPropertyException('Notification URL is required');
        }

        if (empty($this->eventTypes)) {
            throw new MissingPropertyException('At least one event type is required');
        }

        // Validate URL format
        if (!filter_var($this->notificationUrl, FILTER_VALIDATE_URL) || 
            !str_starts_with($this->notificationUrl, 'https://')) {
            throw new MissingPropertyException('Notification URL must be a valid HTTPS URL');
        }
    }

    /**
     * Get the current API version.
     *
     * @return string
     */
    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * Get the current event types.
     *
     * @return array
     */
    public function getEventTypes(): array
    {
        return $this->eventTypes;
    }

    /**
     * Get the current notification URL.
     *
     * @return string|null
     */
    public function getNotificationUrl(): ?string
    {
        return $this->notificationUrl;
    }

    /**
     * Get the current name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
