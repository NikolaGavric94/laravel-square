<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class TaxTest extends TestCase
{
    /**
     * Tax creation.
     *
     * @return void
     */
    public function test_tax_make()
    {
        $tax = factory(Tax::class)->create();

        $this->assertNotNull($tax, 'Tax is null.');
    }

    /**
     * Tax persisting.
     *
     * @return void
     */
    public function test_tax_create()
    {
        $name = $this->faker->name;

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_taxes', [
            'name' => $name,
        ]);
    }

    /**
     * Check order persisting with taxes.
     *
     * @return void
     */
    public function test_tax_create_with_orders()
    {
        $name = $this->faker->name;
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $tax->orders()->attach($order1, ['featurable_type' => Order::class, 'deductible_type' => Constants::TAX_NAMESPACE]);
        $tax->orders()->attach($order2, ['featurable_type' => Order::class, 'deductible_type' => Constants::TAX_NAMESPACE]);

        $this->assertCount(2, $tax->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $tax->orders);
    }

    /**
     * Check product persisting with taxes.
     *
     * @return void
     */
    public function test_tax_create_with_products()
    {
        $name = $this->faker->name;
        $product1 = factory(OrderProductPivot::class)->create();
        $product2 = factory(OrderProductPivot::class)->create();

        $tax = factory(Tax::class)->create([
            'name' => $name,
        ]);

        $tax->products()->attach($product1, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE]);
        $tax->products()->attach($product2, ['featurable_type' => Constants::ORDER_PRODUCT_NAMESPACE, 'deductible_type' => Constants::TAX_NAMESPACE]);

        $this->assertCount(2, $tax->products);
        $this->assertContainsOnlyInstancesOf(Constants::ORDER_PRODUCT_NAMESPACE, $tax->products);
    }
}
