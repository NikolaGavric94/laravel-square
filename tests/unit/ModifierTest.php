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
     * Modifier persisting.
     *
     * @return void
     */
    public function test_modifier_products_relationship(): void
    {
        $name = $this->faker->name;

        $modifier = factory(Modifier::class)->create([
            'name' => $name,
        ]);

        $product = factory(Product::class)->create([
            'name' => $this->faker->name,
        ]);

        // Attach the product to the modifier
        $modifier->products()->attach($product->id);
        $this->assertDatabaseHas('nikolag_modifier_product_pivot', [
            'product_id'  => $product->id,
            'modifier_id' => $modifier->id,
        ]);
    }
}
