<?php

namespace Nikolag\Square\Builders;

use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\OrderProductModifierPivot;
use Nikolag\Square\Models\OrderProductPivot;

class ModifiersBuilder
{
    /**
     * Associate modifiers with the product.
     *
     * @param  OrderProductPivot $productPivot
     * @param  array|Collection  $modifiers
     * @return OrderProductPivot
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function addModifiers(OrderProductPivot $orderProduct, array|Collection $modifiers): OrderProductPivot
    {
        $temp = collect([]);
        foreach ($modifiers as $modifier) {
            $modifierPivot = $this->createProductModifier($orderProduct, $modifier);
            $temp->push($modifierPivot);
        }

        $orderProduct->modifiers = $temp;

        return $orderProduct;
    }

    /**
     * Associate the modifier.
     * @param OrderProductPivot       $productPivot
     * @param Modifier|ModifierOption $modifier
     *
     * @return OrderProductModifierPivot
     */
    public function createProductModifier(OrderProductPivot $orderProduct, Modifier|ModifierOption $modifier): OrderProductModifierPivot
    {
        $productModifier = new OrderProductModifierPivot();

        if ($modifier instanceof Modifier) {
            if ($modifier->type === 'LIST') {
                throw new InvalidSquareOrderException('Modifier LIST type must use specific modifier option', 500);
            } elseif ($modifier->type === 'TEXT' && !$modifier->text) {
                throw new InvalidSquareOrderException('Text is missing for the text modifier', 500);
            }

            $productModifier->text = $modifier->text;
        }

        // Set the quantity of the modifier
        $productModifier->quantity = $modifier->quantity ?? 1;

        if ($orderProduct->id) {
            $productModifier->order_product_id = $orderProduct->id;
        }
        $productModifier->modifiable()->associate($modifier);

        return $productModifier;
    }
}
