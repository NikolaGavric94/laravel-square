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
     * Get the current fulfillment for the order.
     *
     * @return mixed The fulfillment object or null if none set.
     */
    public function getFulfillment(): mixed;

    /**
     * Get the fulfillment details for the current fulfillment.
     *
     * @return mixed The fulfillment details object or null if none set.
     */
    public function getFulfillmentDetails(): mixed;

    /**
     * Get the fulfillment recipient for the current fulfillment.
     *
     * @return mixed The recipient object or null if none set.
     */
    public function getFulfillmentRecipient(): mixed;

    /**
     * Set the fulfillment for the order.
     *
     * Note: Orders can only have one fulfillment as per Square API limitations.
     *
     * @param mixed $fulfillment The fulfillment model or array data.
     * @return SquareServiceContract
     */
    public function setFulfillment(mixed $fulfillment): SquareServiceContract;

    /**
     * Set the recipient for the fulfillment details.
     *
     * @param mixed $recipient The recipient model or array data.
     * @return SquareServiceContract
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
