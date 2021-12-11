<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Utils\Constants;

class TaxesBuilder
{
    /**
     * Find or create tax models
     * from taxes array.
     *
     * @param  array  $taxes
     * @param  string  $scope
     * @param  Model  $parent
     * @return Collection
     *
     * @throws MissingPropertyException
     */
    public function createTaxes(array $taxes, string $scope, Model $parent = null)
    {
        $temp = collect([]);
        foreach ($taxes as $tax) {
            //If percentage doesn't exist on a taxes table
            //throw new exception because it should exist
            if ($tax['percentage'] == null || $tax['percentage'] == 0.0) {
                throw new MissingPropertyException('$percentage property for object Tax is missing or is invalid', 500);
            }
            //Check if parent is present or parent already has this tax or if tax
            //doesn't have property $id then create new Tax object
            if (($parent && ! $parent->hasTax($tax)) || ! Arr::has($tax, 'id')) {
                $tempTax = new Tax($tax);
            } else {
                // Load tax with pivot
                if (Arr::has($tax, 'pivot')) {
                    if ($scope === Constants::DEDUCTIBLE_SCOPE_ORDER) {
                        $orderClass = config('nikolag.connections.square.order.namespace');
                        $tempTax = $orderClass::find($tax['pivot']['featurable_id'])->taxes()->find($tax['id']);
                    } elseif ($scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                        $tempTax = OrderProductPivot::find($tax['pivot']['featurable_id'])->taxes()->find($tax['id']);
                    }
                }
            }

            if (isset($tempTax)) {
                $tempTax->scope = $scope;
                $temp->push($tempTax);
            }
        }

        return $temp;
    }
}
