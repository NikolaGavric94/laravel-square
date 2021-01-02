<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use SquareConnect\Model\CreateCustomerRequest;
use SquareConnect\Model\CreateOrderRequest;
use SquareConnect\Model\CreatePaymentRequest;
use SquareConnect\Model\OrderLineItem;
use SquareConnect\Model\OrderLineItemAppliedDiscount;
use SquareConnect\Model\OrderLineItemAppliedTax;
use SquareConnect\Model\OrderLineItemDiscount;
use SquareConnect\Model\OrderLineItemTax;

class SquareRequestBuilder
{
    /**
     * Item line level taxes which need to be applied to order.
     *
     * @var Collection
     */
    private $productTaxes;
    /**
     * Item line level taxes which need to be applied to order.
     *
     * @var Collection
     */
    private $productDiscounts;

    /**
     * SquareRequestBuilder constructor.
     */
    public function __construct()
    {
        $this->productTaxes = collect([]);
        $this->productDiscounts = collect([]);
    }

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

        // Add product level discounts to order level taxes
        if ($this->productDiscounts->isNotEmpty()) {
            $data['order']['discounts'] = $this->productDiscounts->merge($data['order']['discounts'])->toArray();
            $this->productDiscounts = collect([]);
        }

        // Add product level taxes to order level taxes
        if ($this->productTaxes->isNotEmpty()) {
            $data['order']['taxes'] = $this->productTaxes->merge($data['order']['taxes'])->toArray();
            $this->productTaxes = collect([]);
        }

        return new CreateOrderRequest($data);
    }

    /**
     * Builds and returns array of discounts.
     *
     * @param Collection $discounts
     * @param string $currency
     * @param string $class
     *
     * @return array
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildDiscounts(Collection $discounts, string $currency, string $class = OrderLineItemDiscount::class)
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
                    'uid'  => Util::uid(),
                    'name' => $discount->name,
                    'scope'=> Constants::DISCOUNT_SCOPE_ORDER,
                ];
                //If percentage exists append it
                if ($percentage && $percentage != 0.0) {
                    $data['percentage'] = (string) $percentage;
                    $data['type'] = Constants::DISCOUNT_FIXED_PERCENTAGE;
                }
                //If amount exists append it
                if ($amount && $amount != 0) {
                    $money = [
                        'amount'   => $amount,
                        'currency' => $currency,
                    ];
                    $data['amount_money'] = $money;
                    $data['type'] = Constants::DISCOUNT_FIXED_AMOUNT;
                }

                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied discounts.
     *
     * @param Collection $discounts
     * @param string $class
     *
     * @return array
     */
    public function buildAppliedDiscounts(Collection $discounts, string $class = OrderLineItemAppliedDiscount::class)
    {
        $temp = [];
        if ($discounts->isNotEmpty()) {
            foreach ($discounts as $discount) {
                $data = [
                    'uid'           => Util::uid(),
                    'discount_uid'  => $discount->getUid(),
                ];
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of taxes.
     *
     * @param Collection $taxes
     * @param string $class
     *
     * @return array
     * @throws MissingPropertyException
     */
    public function buildTaxes(Collection $taxes, string $class = OrderLineItemTax::class)
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
                    'uid'           => Util::uid(),
                    'name'          => $tax->name,
                    'type'          => $tax->type,
                    'percentage'    => (string) $percentage,
                    'scope'         => Constants::DISCOUNT_SCOPE_ORDER,
                ];
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied taxes.
     *
     * @param Collection $taxes
     * @param string $class
     *
     * @return array
     */
    public function buildAppliedTaxes(Collection $taxes, string $class = OrderLineItemAppliedTax::class)
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                $data = [
                    'uid'           => Util::uid(),
                    'tax_uid'       => $tax->getUid(),
                ];
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of \SquareConnect\Model\OrderLineItem for order.
     *
     * @param Collection $products
     * @param string $currency
     * @param string $class
     *
     * @return array
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildProducts(Collection $products, string $currency, string $class = OrderLineItem::class)
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
                //Build product level taxes so we can append them to order later
                $taxes = collect($this->buildTaxes($pivotProduct->taxes));
                $this->productTaxes = $this->productTaxes->merge($taxes);

                //Build product level discounts so we can append them to order later
                $discounts = collect($this->buildDiscounts($pivotProduct->discounts, $currency));
                $this->productDiscounts = $this->productDiscounts->merge($discounts);

                $data = [
                    'name' => $product->name,
                    'quantity' => (string) $quantity,
                    'base_price_money' => $money,
                    'variation_name' => $product->variation_name,
                    'note' => $product->note,
                    'applied_taxes' => $this->buildAppliedTaxes($taxes),
                    'applied_discounts' => $this->buildAppliedDiscounts($discounts),
                ];
                array_push($temp, new $class($data));
            }
        }

        return $temp;
    }
}
