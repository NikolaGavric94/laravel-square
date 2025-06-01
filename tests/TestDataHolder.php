<?php

namespace Nikolag\Square\Tests;

use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Square\Models\FulfillmentType;
use Square\Models\Money;
use Square\Models\OrderMoneyAmounts;
use Square\Models\OrderReturn as SquareOrderReturn;
use Square\Models\OrderReturnLineItem;

class TestDataHolder
{
    public function __construct(
        public ?Order $order,
        public ?Product $product,
        public ?Customer $customer,
        public ?User $merchant,
        public ?Tax $tax,
        public ?Discount $discount,
        public ?Fulfillment $fulfillmentWithDeliveryDetails,
        public ?Fulfillment $fulfillmentWithPickupDetails,
        public ?Fulfillment $fulfillmentWithShipmentDetails,
        public ?Recipient $fulfillmentRecipient,
        public ?SquareOrderReturn $squareOrderReturn,
    ) {
    }

    public static function make(): self
    {
        return new static(
            factory(Order::class)->make(),
            factory(Product::class)->make(),
            factory(Customer::class)->make(),
            factory(User::class)->make(),
            factory(Tax::class)->make(),
            factory(Discount::class)->states('AMOUNT_ONLY')->make(),
            factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make(),
            factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make(),
            factory(Fulfillment::class)->states(FulfillmentType::SHIPMENT)->make(),
            factory(Recipient::class)->make(),
            self::buildMockOrderReturn()
        );
    }

    public static function create(): self
    {
        return new static(
            factory(Order::class)->create(),
            factory(Product::class)->create(),
            factory(Customer::class)->create(),
            factory(User::class)->create(),
            factory(Tax::class)->create(),
            factory(Discount::class)->states('AMOUNT_ONLY')->create(),
            // NOTE: The following factories are not created because they are not associated with the order
            factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make(),
            factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make(),
            factory(Fulfillment::class)->states(FulfillmentType::SHIPMENT)->make(),
            factory(Recipient::class)->create(),
            self::buildMockOrderReturn()
        );
    }

    public function modify(string $prodFac = 'create',
                           string $prodDiscFac = 'create',
                           string $orderDisFac = 'create',
                           string $orderDiscFixFac = 'create',
                           string $taxAddFac = 'create',
                           string $taxIncFac = 'create')
    {
        $product = factory(Product::class)->{$prodFac}([
            'price' => 1000,
        ]);
        $productDiscount = factory(Discount::class)->states('AMOUNT_ONLY')->{$prodDiscFac}([
            'amount' => 50,
        ]);
        $orderDiscount = factory(Discount::class)->states('PERCENTAGE_ONLY')->{$orderDisFac}([
            'percentage' => 10.0,
        ]);
        $orderDiscountFixed = factory(Discount::class)->states('AMOUNT_ONLY')->{$orderDiscFixFac}([
            'amount' => 250.0,
        ]);
        $taxAdditive = factory(Tax::class)->states('ADDITIVE')->{$taxAddFac}([
            'percentage' => 10.0,
        ]);
        $taxInclusive = factory(Tax::class)->states('INCLUSIVE')->{$taxIncFac}([
            'percentage' => 15.0,
        ]);

        return compact('product', 'productDiscount', 'orderDiscount', 'orderDiscountFixed', 'taxAdditive', 'taxInclusive');
    }

    /**
     * Builds a recipient array for the order.
     *
     * @return array
     */
    public static function buildRecipientArray(): array
    {
        return [
            'display_name' => 'John Doe',
            'email_address' => 'johndoe@test.com',
            'phone_number' => '1234567890',
            'address' => [
                'address_line_1' => '123 Main St',
                'locality' => 'San Francisco',
                'administrative_district_level_1' => 'CA',
                'postal_code' => '94114',
                'country' => 'US',
            ],
        ];
    }



    /**
     * Builds a reusable mock OrderReturn model from square for testing.
     *
     * @return SquareOrderReturn
     */
    private static function buildMockOrderReturn(): SquareOrderReturn
    {
        $mockOrderReturn = new SquareOrderReturn();
        $mockOrderReturn->setUid('mock-return-uid');
        $mockOrderReturn->setSourceOrderId('mock-source-order-id');

        // Create a re-usable money object
        $money = new Money();
        $money->setCurrency('USD');

        // Build the order money amount that stores everything
        $returnAmounts = new OrderMoneyAmounts();

        // Total
        $totalMoney = clone $money;
        $totalMoney->setAmount(20_00); // $20.00 USD
        $returnAmounts->setTotalMoney($totalMoney); // $20.00 USD

        // Tax
        $taxMoney = clone $money;
        $taxMoney->setAmount(2_00); // $2.00 USD
        $returnAmounts->setTaxMoney($taxMoney); // $2.00 USD

        // Discount
        $discountMoney = clone $money;
        $discountMoney->setAmount(1_00); // $1.00 USD
        $returnAmounts->setDiscountMoney($discountMoney); // $1.00 USD

        // Tip
        $tipMoney = clone $money;
        $tipMoney->setAmount(1_50); // $1.50 USD
        $returnAmounts->setTipMoney($tipMoney); // $1.50 USD

        // Service Charge
        $serviceChargeMoney = clone $money;
        $serviceChargeMoney->setAmount(50); // $0.50 USD
        $returnAmounts->setServiceChargeMoney($serviceChargeMoney); // $0.50 USD

        // Set the return amounts on the mock order return
        $mockOrderReturn->setReturnAmounts($returnAmounts);

        // Set the line items
        $lineItem1Money = clone $money;
        $lineItem2Money = clone $money;
        $lineItem1Money->setAmount(10_00); // $10.00 USD
        $lineItem2Money->setAmount(3_00); // $3.00 USD
        $lineItems = [
            [
                'uid' => 'line-item-1',
                'source_line_item_uid' => 'source-line-item-1',
                'name' => 'Test Item 1',
                'quantity' => 1,
                'total_money' => $lineItem1Money,
            ],
            [
                'uid' => 'line-item-2',
                'source_line_item_uid' => 'source-line-item-2',
                'name' => 'Test Item 2',
                'quantity' => 2,
                'total_money' => $lineItem2Money,
            ],
        ];

        $returnLineItems = [];
        foreach ($lineItems as $item) {
            $returnLineItem = new OrderReturnLineItem($item['quantity']);
            $returnLineItem->setUid($item['uid']);
            $returnLineItem->setSourceLineItemUid($item['source_line_item_uid']);
            $returnLineItem->setName($item['name']);
            $returnLineItem->setTotalMoney($item['total_money']);
            $returnLineItems[] = $returnLineItem;
        }
        $mockOrderReturn->setReturnLineItems($returnLineItems);

        // Skipping rounding adjustment for simplicity
        // $mockOrderReturn->setRoundingAdjustment(null);

        return $mockOrderReturn;
    }
}
