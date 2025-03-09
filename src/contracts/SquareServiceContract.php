<?php

namespace Nikolag\Square\Contracts;

use Nikolag\Core\Contracts\PaymentServiceContract;

interface SquareServiceContract extends PaymentServiceContract
{
    /**
     * Add a product to the order.
     *
     * @param  mixed  $product
     * @param  int  $quantity
     * @param  string  $currency
     * @return self
     */
    public function addProduct(mixed $product, int $quantity = 1, string $currency = 'USD'): SquareServiceContract;

    /**
     * Getter for fulfillment.
     *
     * @return mixed
     */
    public function getFulfillment(): mixed;

    /**
     * @return mixed
     */
    public function getFulfillmentDetails(): mixed;

    /**
     * Getter for fulfillment recipient.
     *
     * @param  mixed  $recipient
     * @return self
     */
    public function getFulfillmentRecipient(): mixed;

    /**
     * Sets the fulfillment for the order.
     *
     * @param  mixed  $fulfillment
     * @return self
     */
    public function setFulfillment(mixed $fulfillment): SquareServiceContract;

    /**
     * Add a fulfillment recipient to the order.
     *
     * @param  mixed  $recipient
     * @return self
     */
    public function setFulfillmentRecipient(mixed $recipient): SquareServiceContract;

    /**
     * Setter for order.
     *
     * @param  mixed  $order
     * @param  string  $locationId
     * @param  string  $currency
     * @return self
     */
    public function setOrder(mixed $order, string $locationId, string $currency = 'USD'): SquareServiceContract;
}
