<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Tax;

class TaxesBuilder
{
    /**
     * Find or create tax models
     * from taxes array.
     *
     * @param array $taxes
     * @param Model $parent
     *
     * @return \Illuminate\Support\Collection
     * @throws MissingPropertyException
     */
    public function createTaxes(array $taxes, Model $parent = null)
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
                $tempTax = Tax::find($tax['id']);
            }
            $temp->push($tempTax);
        }

        return $temp;
    }
}
