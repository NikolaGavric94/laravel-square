<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\CreateCustomerRequest;
use Square\Models\CreateOrderRequest;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Models\Order;
use Square\Models\OrderLineItem;
use Square\Models\OrderLineItemAppliedDiscount;
use Square\Models\OrderLineItemAppliedTax;
use Square\Models\OrderLineItemDiscount;
use Square\Models\OrderLineItemTax;
use Square\Models\UpdateCustomerRequest;

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
     * @param  array  $prepData
     * @return CreatePaymentRequest
     */
    public function buildChargeRequest(array $prepData)
    {
        $money = new Money();
        $money->setCurrency($prepData['amount_money']['currency']);
        $money->setAmount($prepData['amount_money']['amount']);
        $request = new CreatePaymentRequest($prepData['source_id'], $prepData['idempotency_key'], $money);
        $request->setAutocomplete($prepData['autocomplete']);
        $request->setLocationId($prepData['location_id']);
        $request->setNote($prepData['note']);
        $request->setVerificationToken($prepData['verification_token']);
        $request->setReferenceId($prepData['reference_id']);

        return $request;
    }

    /**
     * Create and return customer request.
     *
     * @param  Model  $customer
     * @return CreateCustomerRequest|UpdateCustomerRequest
     */
    public function buildCustomerRequest(Model $customer)
    {
        if ($customer->payment_service_id) {
            $request = new UpdateCustomerRequest();
        } else {
            $request = new CreateCustomerRequest();
        }
        $request->setGivenName($customer->first_name);
        $request->setFamilyName($customer->last_name);
        $request->setCompanyName($customer->company_name);
        $request->setNickname($customer->nickname);
        $request->setEmailAddress($customer->email);
        $request->setPhoneNumber($customer->phone);
        $request->setReferenceId($customer->owner_id);
        $request->setNote($customer->note);

        return $request;
    }

    /**
     * Create and return order request.
     *
     * @param  Model  $order
     * @param  string  $locationId
     * @param  string  $currency
     * @return CreateOrderRequest
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildOrderRequest(Model $order, string $locationId, string $currency)
    {
        $squareOrder = new Order($locationId);
        $squareOrder->setReferenceId($order->id);
        $squareOrder->setLineItems($this->buildProducts($order->products, $currency));
        $squareOrder->setDiscounts($this->buildDiscounts($order->discounts, $currency));
        $squareOrder->setTaxes($this->buildTaxes($order->taxes));
        $request = new CreateOrderRequest();
        $request->setOrder($squareOrder);
        $request->setIdempotencyKey(uniqid());

        return $request;
    }

    /**
     * Builds and returns array of discounts.
     *
     * @param  Collection  $discounts
     * @param  string  $currency
     * @return array
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function buildDiscounts(Collection $discounts, string $currency)
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
                $tempDiscount = new OrderLineItemDiscount();
                $tempDiscount->setUid(Util::uid());
                $tempDiscount->setName($discount->name);
                $tempDiscount->setScope($discount->pivot->scope);

                // If it's LINE ITEM then assign proper UID
                if ($discount->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                    $found = $this->productDiscounts->first(function ($disc) use ($discount) {
                        return $disc->getName() === $discount->name;
                    });

                    if ($found) {
                        $tempDiscount->setUid($found->getUid());
                    }
                }
                //If percentage exists append it
                if ($percentage && $percentage != 0.0) {
                    $tempDiscount->setPercentage((string) $percentage);
                    $tempDiscount->setType(Constants::DEDUCTIBLE_FIXED_PERCENTAGE);
                }
                //If amount exists append it
                if ($amount && $amount != 0) {
                    $money = new Money();
                    $money->setAmount($amount);
                    $money->setCurrency($currency);
                    $tempDiscount->setAmountMoney($money);
                    $tempDiscount->setType(Constants::DEDUCTIBLE_FIXED_AMOUNT);
                }

                array_push($temp, $tempDiscount);
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied discounts.
     *
     * @param  Collection  $discounts
     * @return array
     */
    public function buildAppliedDiscounts(Collection $discounts)
    {
        $temp = [];
        if ($discounts->isNotEmpty()) {
            foreach ($discounts as $discount) {
                $tempDiscount = new OrderLineItemAppliedDiscount($discount->getUid());
                $tempDiscount->setUid(Util::uid());
                array_push($temp, $tempDiscount);
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of taxes.
     *
     * @param  Collection  $taxes
     * @return array
     *
     * @throws MissingPropertyException
     */
    public function buildTaxes(Collection $taxes)
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                $tempTax = new OrderLineItemTax();
                //If percentage doesn't exist in tax table
                //throw new exception because it should exist
                $percentage = $tax->percentage;
                if ($percentage == null || $percentage == 0.0) {
                    throw new MissingPropertyException('$percentage property for object Tax is missing or is invalid', 500);
                }

                $tempTax->setUid(Util::uid());
                $tempTax->setName($tax->name);
                $tempTax->setType($tax->type);
                $tempTax->setPercentage((string) $percentage);
                $tempTax->setScope($tax->pivot->scope);

                // If it's LINE ITEM then assign proper UID
                if ($tax->pivot->scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                    $found = $this->productTaxes->first(function ($inner) use ($tax) {
                        return $inner->getName() === $tax->name;
                    });

                    if ($found) {
                        $tempTax->setUid($found->getUid());
                    }
                }

                array_push($temp, $tempTax);
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of already applied taxes.
     *
     * @param  Collection  $taxes
     * @param  string  $class
     * @return array
     */
    public function buildAppliedTaxes(Collection $taxes)
    {
        $temp = [];
        if ($taxes->isNotEmpty()) {
            foreach ($taxes as $tax) {
                $tempTax = new OrderLineItemAppliedTax($tax->getUid());
                $tempTax->setUid(Util::uid());
                array_push($temp, $tempTax);
            }
        }

        return $temp;
    }

    /**
     * Builds and returns array of \SquareConnect\Model\OrderLineItem for order.
     *
     * @param  Collection  $products
     * @param  string  $currency
     * @param  string  $class
     * @return array
     *
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

                //Build product level taxes so we can append them to order later
                $taxes = collect($this->buildTaxes($pivotProduct->taxes));
                $this->productTaxes = $this->productTaxes->merge($taxes);

                //Build product level discounts so we can append them to order later
                $discounts = collect($this->buildDiscounts($pivotProduct->discounts, $currency));
                $this->productDiscounts = $this->productDiscounts->merge($discounts);

                $money = new Money();
                $money->setAmount($product->price);
                $money->setCurrency($currency);
                $tempProduct = new OrderLineItem($quantity);
                $tempProduct->setName($product->name);
                $tempProduct->setBasePriceMoney($money);
                $tempProduct->setQuantity((string) $quantity);
                $tempProduct->setVariationName($product->variation_name);
                $tempProduct->setNote($product->note);
                $tempProduct->setAppliedDiscounts($this->buildAppliedDiscounts($discounts));
                $tempProduct->setAppliedTaxes($this->buildAppliedTaxes($taxes));
                array_push($temp, $tempProduct);
            }
        }

        return $temp;
    }
}
