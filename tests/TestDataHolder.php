<?php
namespace Nikolag\Square\Tests;

use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Utils\Util;

class TestDataHolder {
    public function __construct(public ?Order    $order,
                                public ?Product  $product,
                                public ?Customer $customer,
                                public ?User     $merchant,
                                public ?Tax      $tax,
                                public ?Discount $discount) {}

    public static function make(): self {
        return new static(factory(Order::class)->make(),
            factory(Product::class)->make(),
            factory(Customer::class)->make(),
            factory(User::class)->make(),
            factory(Tax::class)->make(),
            factory(Discount::class)->states('AMOUNT_ONLY')->make());
    }

    public static function create(): self {
        return new static(factory(Order::class)->create(),
            factory(Product::class)->create(),
            factory(Customer::class)->create(),
            factory(User::class)->create(),
            factory(Tax::class)->create(),
            factory(Discount::class)->states('AMOUNT_ONLY')->create());
    }

    public function modify(string $prodFac = "create",
                           string $prodDiscFac = "create",
                           string $orderDisFac = "create",
                           string $orderDiscFixFac = "create",
                           string $taxAddFac = "create",
                           string $taxIncFac = "create") {
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
}