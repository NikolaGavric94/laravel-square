<?php

namespace Nikolag\Square\Tests\Unit;

use Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\ModifierOptionLocationPivot;
use Nikolag\Square\Models\OrderProductModifierPivot;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;

class OrderProductModifierPivotTest extends TestCase
{
    /**
     * Modifier creation.
     *
     * @return void
     */
    public function test_product_modifier_make(): void
    {
        $productModifier = factory(OrderProductModifierPivot::class)->make();

        $this->assertNotNull($productModifier, 'Order Product Modifier Pivot is null.');
    }

    /**
     * Modifier persisting.
     *
     * @return void
     */
    public function test_product_modifier_create(): void
    {
        // Make an order, a product and an order product pivot
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();
        $orderProduct = factory(OrderProductPivot::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);
        $productModifier = factory(OrderProductModifierPivot::class)->create([
            'order_product_id' => $orderProduct->id,
        ]);

        $this->assertNotNull($productModifier, 'Order Product Modifier Pivot is null.');

        $this->assertDatabaseHas('nikolag_product_order_modifier', [
            'modifiable_type' => ModifierOption::class,
            'order_product_id' => $orderProduct->id,
        ]);
    }
}
