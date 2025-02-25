<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;

class ModifierTest extends TestCase
{
    /**
     * Modifier creation.
     *
     * @return void
     */
    public function test_modifier_make(): void
    {
        $modifier = factory(Modifier::class)->make();

        $this->assertNotNull($modifier, 'Modifier is null.');
    }

    /**
     * Modifier persisting.
     *
     * @return void
     */
    public function test_modifier_create(): void
    {
        $name = $this->faker->name;

        $modifier = factory(Modifier::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_modifiers', [
            'name' => $name,
        ]);
    }

    /**
     * Check Modifier persisting with orders.
     *
     * @return void
     */
    public function test_modifier_create_with_product_order(): void
    {
        $name = $this->faker->name;

        // Create a new modifier and modifier option
        $modifier = factory(Modifier::class)->create([
            'name' => $name,
        ]);

        // This factory also creates a new order and product
        $orderProduct = factory(OrderProductPivot::class)->create();
        $orderProduct->modifier()->associate($modifier->id);

        $this->assertNotEmpty($orderProduct->modifier);
        $this->assertInstanceOf(Modifier::class, $orderProduct->modifier);
    }

    /**
     * Check Modifier persisting with orders.
     *
     * @return void
     */
    public function test_modifier_and_option_create_with_product_order(): void
    {
        $name = $this->faker->name;

        // Create a new modifier and modifier option
        $modifier = factory(Modifier::class)->create([
            'name' => $name,
        ]);
        $modifierOption = factory(ModifierOption::class)->create([
            'name' => $name,
            'modifier_id' => $modifier->id,
        ]);

        // This factory also creates a new order and product
        $orderProduct = factory(OrderProductPivot::class)->create();
        $orderProduct->modifier()->associate($modifier->id);
        $orderProduct->modifierOption()->associate($modifierOption->id);

        $this->assertNotEmpty($orderProduct->modifier);
        $this->assertInstanceOf(Modifier::class, $orderProduct->modifier);
        $this->assertNotEmpty($orderProduct->modifierOption);
        $this->assertInstanceOf(ModifierOption::class, $orderProduct->modifierOption);
    }
}
