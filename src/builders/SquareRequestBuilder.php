<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use SquareConnect\Model\CreateCustomerRequest;
use SquareConnect\Model\CreateOrderRequest;
use SquareConnect\Model\CreateOrderRequestDiscount;
use SquareConnect\Model\CreateOrderRequestLineItem;
use SquareConnect\Model\CreateOrderRequestTax;
use SquareConnect\Model\CreatePaymentRequest;

class SquareRequestBuilder
{
    /**
     * Create and return charge request.
     *
     * @param array $prepData
     *
     * @return \SquareConnect\Model\CreatePaymentRequest
     */
    public function buildChargeRequest(array $prepData)
    {
        return new CreatePaymentRequest($prepData);
    }

    /**
     * Create and return customer request.
     *
     * @param Model $customer
     *
     * @return \SquareConnect\Model\CreateCustomerRequest
     */
    public function buildCustomerRequest(Model $customer)
    {
        $data = [
            'given_name'    => $customer->first_name,
            'family_name'   => $customer->last_name,
            'company_name'  => $customer->company_name,
            'nickname'      => $customer->nickname,
            'email_address' => $customer->email,
            'phone_number'  => $customer->phone,
            'reference_id'  => $customer->owner_id,
            'note'          => $customer->note,
        ];

        return new CreateCustomerRequest($data);
    }

    /**
     * Create and return order request.
     *
     * @param Model $order
     * @param string $currency
     *
     * @return \SquareConnect\Model\CreateOrderRequest
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildOrderRequest(Model $order, string $currency)
    {
        $data = [
            'idempotency_key' => uniqid(),
            'order'           => [
                'reference_id'=> (string) $order->id,
                'line_items'  => $this->buildProducts($order->products, $currency),
                'discounts'   => $this->buildDiscounts($order->discounts, $currency),
                'taxes'       => $this->buildTaxes($order->taxes),
            ],
        ];

        return new CreateOrderRequest($data);
    }

    /**
     * Builds and returns array of discounts for a \SquareConnect\Model\CreateOrderRequestDiscount.
     *
     * @param Collection $discounts
     * @param string $currency
     * @param string $class
     *
     * @return array
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildDiscounts(Collection $discounts, string $currency, string $class = CreateOrderRequestDiscount::class)
    {
        $temp = [];
        if ($discounts->isNotEmpty()) {
            foreach ($discounts as $discount) {
                //If discount doesn't have amount OR percentage in discount table
                //throw new exception because it should have at least 1
                $amount = $discount->amount;
                $percentage = $discount->percentage;
                if (($amount == null || $amount == 0) && ($percentage == null || $percentage == 0.0)) {
                    throw new MissingPropertyException('Both $amount and $percentage property for object Discount are missing, 1 is required', 500);
                }
                //If discount have amount AND percentage in discount table
                //throw new exception because it should only 1
                if (($amount != null || $amount != 0) && ($percentage != null || $percentage != 0.0)) {
                    throw new InvalidSquareOrderException('Both $amount and $percentage exist for object Discount, only 1 is allowed', 500);
                }
                $data = [
                    'name' => $discount->name,
                ];
                //If percentage exists append it
                if ($percentage && $percentage != 0.0) {
                    $data['percentage'] = (string) $percentage;
                }
                //If amount exists append it
                if ($amount && $amount != 0) {
                    $money = [
                        'amount'   => $amount,
                        'currency' => $currency,
                    ];
                    $data['amount_money'] = $money;
                }
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of taxes for a \SquareConnect\Model\CreateOrderRequestTax.
     *
     * @param Collection $taxes
     * @param string $class
     *
     * @return array
     * @throws MissingPropertyException
     */
    public function buildTaxes(Collection $taxes, string $class = CreateOrderRequestTax::class)
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                //If percentage doesn't exist tax table
                //throw new exception because it should exist
                $percentage = $tax->percentage;
                if ($percentage == null || $percentage == 0.0) {
                    throw new MissingPropertyException('$percentage property for object Tax is missing or is invalid', 500);
                }
                $data = [
                    'name'       => $tax->name,
                    'type'       => $tax->type,
                    'percentage' => (string) $percentage,
                ];
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of \SquareConnect\Model\CreateOrderRequestLineItem for order.
     *
     * @param Collection $products
     * @param string $currency
     *
     * @return array
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildProducts(Collection $products, string $currency)
    {
        $temp = [];
        if ($products->isNotEmpty()) {
            foreach ($products as $product) {
                // Get product from pivot model
                $pivotProduct = $product->pivot;
                //If product doesn't have quantity
                //throw new exception because every product should
                //have at least 1 quantity
                $quantity = $pivotProduct->quantity;
                if ($quantity == null || $quantity == 0) {
                    throw new MissingPropertyException('$quantity property for object Product is missing', 500);
                }
                $money = [
                    'amount' => $product->price,
                    'currency' => $currency,
                ];

                $data = [
                    'name' => $product->name,
                    'quantity' => (string) $quantity,
                    'base_price_money' => $money,
                    'variation_name' => $product->variation_name,
                    'note' => $product->note,
                    'taxes' => $this->buildTaxes($pivotProduct->taxes),
                    'discounts' => $this->buildDiscounts($pivotProduct->discounts, $currency),
                ];
                array_push($temp, new CreateOrderRequestLineItem($data));
            }
        }

        return $temp;
    }
}
