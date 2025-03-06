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

class ModifierOptionTest extends TestCase
{
    /**
     * Modifier creation.
     *
     * @return void
     */
    public function test_modifier_option_make(): void
    {
        $modifier = factory(ModifierOption::class)->make();

        $this->assertNotNull($modifier, 'Modifier option is null.');
    }

    /**
     * Modifier persisting.
     *
     * @return void
     */
    public function test_modifier_option_create(): void
    {
        $name = $this->faker->name;

        $modifier = factory(ModifierOption::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_modifier_options', [
            'name' => $name,
        ]);
    }
}
