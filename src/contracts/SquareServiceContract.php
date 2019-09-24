<?php

namespace Nikolag\Square\Contracts;

use Nikolag\Core\Contracts\PaymentServiceContract;

interface SquareServiceContract extends PaymentServiceContract
{
    /**
     * Add a product to the order.
     *
     * @param mixed  $product
     * @param int    $quantity
     * @param string $currency
     *
     * @return self
     */
    public function addProduct($product, int $quantity = 1, string $currency = 'USD');

    /**
     * Setter for order.
     *
     * @param mixed  $order
     * @param string $locationId
     * @param string $currency
     *
     * @return self
     */
    public function setOrder($order, string $locationId, string $currency = 'USD');
}
