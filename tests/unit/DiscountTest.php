<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class DiscountTest extends TestCase
{
    /**
     * Discount creation.
     *
     * @return void
     */
    public function test_discount_make()
    {
        $discount = factory(Discount::class)->create();

        $this->assertNotNull($discount, 'Discount is null.');
    }

    /**
     * Discount persisting.
     *
     * @return void
     */
    public function test_discount_create()
    {
        $name = $this->faker->name;

        $tax = factory(Discount::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_discounts', [
            'name' => $name,
        ]);
    }

    /**
     * Check discount persisting with products.
     *
     * @return void
     */
    public function test_discount_create_with_products()
    {
        $name = $this->faker->name;
        $product1 = factory(OrderProductPivot::class)->create();
        $product2 = factory(OrderProductPivot::class)->create();

        $discount = factory(Discount::class)->create([
            'name' => $name,
        ]);

        $discount->products()->attach($product1, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);
        $discount->products()->attach($product2, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);

        $this->assertCount(2, $discount->products);
        $this->assertContainsOnlyInstancesOf(Constants::ORDER_PRODUCT_NAMESPACE, $discount->products);
    }

    /**
     * Check discount persisting with orders.
     *
     * @return void
     */
    public function test_discount_create_with_orders()
    {
        $name = $this->faker->name;
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $discount = factory(Discount::class)->create([
            'name' => $name,
        ]);

        $discount->orders()->attach($order1, ['featurable_type' => Order::class, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);
        $discount->orders()->attach($order2, ['featurable_type' => Order::class, 'deductible_type' => Constants::DISCOUNT_NAMESPACE]);

        $this->assertCount(2, $discount->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $discount->orders);
    }
}
