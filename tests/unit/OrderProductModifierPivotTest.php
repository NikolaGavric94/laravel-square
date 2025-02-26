<?php

namespace Nikolag\Square\Tests\Unit;

use Exception;
use Illuminate\Support\Collection;
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

    /**
     * Retrieving orderProduct pivot.
     *
     * @return void
     */
    public function test_product_modifier_order_product_pivot_association(): void
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

        $this->assertEquals($orderProduct->id, $productModifier->orderProduct->id, 'Order Product Modifier Pivot is not associated with Order Product Pivot.');
    }

    /**
     * Check we can create temp modifier pivots and associate data with them properly.
     *
     * @return void
     */
    public function test_product_modifier_associate_modifier(): void
    {
        $modifier = factory(Modifier::class)->create();
        $productModifier = factory(OrderProductModifierPivot::class)->make();

        $productModifier = new OrderProductModifierPivot();
        $productModifier->modifiable()->associate($modifier);

        $this->assertInstanceOf(Modifier::class, $productModifier->modifiable);
    }

    /**
     * Check we can create temp modifier pivots and associate data with them properly.
     *
     * @return void
     */
    public function test_product_modifier_associate_modifier_option(): void
    {
        $modifierOption = factory(ModifierOption::class)->create();
        $productModifier = factory(OrderProductModifierPivot::class)->make();

        $productModifier = new OrderProductModifierPivot();
        $productModifier->modifiable()->associate($modifierOption);

        $this->assertInstanceOf(ModifierOption::class, $productModifier->modifiable);
    }

    /**
     * Order Product pivot creation with modifier option and without modifier id, testing exception case.
     *
     * @return void
     */
    public function test_add_product_modifier_to_square_order(): void
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();

        // Create a list-based modifier option
        $modifierOption = factory(ModifierOption::class)->create();

        $square = Square::setOrder($order, env('SQUARE_LOCATION'))
            ->addProduct($product, 1, modifiers: [$modifierOption])
            ->save();

        $this->assertNotNull($square->getOrder()->products->first()->pivot->modifiers);
        $this->assertInstanceOf(Collection::class, $square->getOrder()->products->first()->pivot->modifiers);
        $this->assertEquals(1, $square->getOrder()->products->first()->pivot->modifiers->count());

        $modifierOptionPivot = $square->getOrder()->products->first()->pivot->modifiers->first();
        $this->assertEquals($modifierOption->id, $modifierOptionPivot->modifiable_id);
        $this->assertEquals(ModifierOption::class, $modifierOptionPivot->modifiable_type);
    }
}
