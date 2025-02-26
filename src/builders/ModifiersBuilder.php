<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\OrderProductModifierPivot;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Utils\Constants;

class ModifiersBuilder
{
    /**
     * Associate modifiers with the product.
     *
     * @param  OrderProductPivot $productPivot
     * @param  array             $modifiers
     * @return OrderProductPivot
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function addModifiers(OrderProductPivot $orderProduct, array $modifiers): OrderProductPivot
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
     * Associate the text modifier.
     * @param OrderProductPivot       $productPivot
     * @param Modifier|ModifierOption $modifier
     *
     * @return OrderProductModifierPivot
     */
    public function createProductModifier(OrderProductPivot $orderProduct, Modifier|ModifierOption $modifier): OrderProductModifierPivot
    {
        if (
            $modifier instanceof Modifier
            && $modifier->type === 'TEXT' && !$modifier->text
        ) {
            throw new InvalidSquareOrderException('Text is missing for the text modifier', 500);
        }

        $productModifier = new OrderProductModifierPivot();
        if ($orderProduct->id) {
            $productModifier->order_product_id = $orderProduct->id;
        }
        $productModifier->modifiable()->associate($modifier);

        return $productModifier;
    }
}
