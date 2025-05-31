<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\ServiceCharge;
use Nikolag\Square\Utils\Constants;

class ServiceChargesBuilder
{
    /**
     * Find or create service charge models
     * from service charges array.
     *
     * @param  array  $serviceCharges
     * @param  string  $scope
     * @param  Model|null  $parent
     * @return Collection
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function createServiceCharges(array $serviceCharges, string $scope, Model $parent = null): Collection
    {
        $temp = collect([]);
        foreach ($serviceCharges as $serviceCharge) {
            //If service charge doesn't have amount_money AND percentage in service charges table
            //throw new exception because it should have at least 1
            if ((! isset($serviceCharge['amount_money']) || $serviceCharge['amount_money'] == null || $serviceCharge['amount_money'] == 0) &&
                (! isset($serviceCharge['percentage']) || $serviceCharge['percentage'] == null || $serviceCharge['percentage'] == 0.0)) {
                throw new MissingPropertyException('Both $amount_money and $percentage property for object ServiceCharge are missing, 1 is required', 500);
            }
            //If service charge have amount_money AND percentage in service charge table
            //throw new exception because it should only 1
            if ((isset($serviceCharge['amount_money']) && ($serviceCharge['amount_money'] != null && $serviceCharge['amount_money'] != 0)) &&
                (isset($serviceCharge['percentage']) && ($serviceCharge['percentage'] != null && $serviceCharge['percentage'] != 0.0))
            ) {
                throw new InvalidSquareOrderException('Both $amount_money and $percentage exist for object ServiceCharge, only 1 is allowed', 500);
            }
            //Check if parent is present or parent already has this service charge or if service charge
            //doesn't have property $id then create new ServiceCharge object
            if (($parent && ! $parent->hasServiceCharge($serviceCharge)) || ! Arr::has($serviceCharge, 'id')) {
                $tempServiceCharge = new ServiceCharge($serviceCharge);
            } else {
                // Load service charge with pivot
                if (Arr::has($serviceCharge, 'pivot')) {
                    if ($scope === Constants::DEDUCTIBLE_SCOPE_ORDER) {
                        $orderClass = config('nikolag.connections.square.order.namespace');
                        $tempServiceCharge = $orderClass::find($serviceCharge['pivot']['featurable_id'])->serviceCharges()->find($serviceCharge['id']);
                    } elseif ($scope === Constants::DEDUCTIBLE_SCOPE_PRODUCT) {
                        $tempServiceCharge = OrderProductPivot::find($serviceCharge['pivot']['featurable_id'])->serviceCharges()->find($serviceCharge['id']);
                    }
                }
            }

            if (isset($tempServiceCharge)) {
                $tempServiceCharge->scope = $scope;
                $temp->push($tempServiceCharge);
            }
        }

        return $temp;
    }
}
