<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Nikolag\Square\Utils\Constants;
use Square\Models\OrderServiceChargeCalculationPhase;
use Square\Models\OrderServiceChargeTreatmentType;

class ServiceCharge extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_service_charges';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'amount_money',
        'amount_currency',
        'percentage',
        'calculation_phase',
        'taxable',
        'treatment_type',
        'reference_id',
        'square_catalog_object_id',
        'square_created_at',
        'square_updated_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'amount_money' => 'integer',
        'percentage' => 'float',
        'taxable' => 'boolean',
        'square_created_at' => 'datetime',
        'square_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model and set up event listeners.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($serviceCharge) {
            $serviceCharge->validateServiceChargeType();
            $serviceCharge->validateCalculationPhaseConstraints();
        });

        static::updating(function ($serviceCharge) {
            $serviceCharge->validateServiceChargeType();
            $serviceCharge->validateCalculationPhaseConstraints();
        });
    }

    //
    // Accessors and Mutators
    //

    /**
     * Set the percentage attribute and clear amount_money.
     *
     * @param mixed $value
     * @return void
     */
    public function setPercentageAttribute($value)
    {
        if (!is_null($this->amount_money) && !is_null($value)) {
            throw ValidationException::withMessages([
                'service_charge' => 'Service charge cannot have percentage while amount_money is set.'
            ]);
        }

        $this->attributes['percentage'] = $value;
    }

    /**
     * Set the amount_money attribute and clear percentage.
     *
     * @param mixed $value
     * @return void
     */
    public function setAmountMoneyAttribute($value)
    {
        if (!is_null($this->amount_money) && !is_null($value)) {
            throw ValidationException::withMessages([
                'service_charge' => 'Service charge cannot have amount_money while percentage is set.'
            ]);
        }

        $this->attributes['amount_money'] = $value;
    }

    //
    // Boolean Checks
    //

    /**
     * Check if this service charge is percentage-based.
     *
     * @return bool
     */
    public function isPercentage(): bool
    {
        return !is_null($this->percentage) && $this->percentage !== 0;
    }

    /**
     * Check if this service charge is fixed amount-based.
     *
     * @return bool
     */
    public function isFixedAmount(): bool
    {
        return !is_null($this->amount_money) && $this->amount_money !== 0;
    }

    //
    // Relationships
    //

    /**
     * Return a list of orders which use this service charge.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function orders()
    {
        return $this->morphToMany(config('nikolag.connections.square.order.namespace'), 'deductible', 'nikolag_deductibles', 'deductible_id', 'featurable_id');
    }

    /**
     * Return a list of products which use this service charge.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function products()
    {
        return $this->morphToMany(Constants::ORDER_PRODUCT_NAMESPACE, 'deductible', 'nikolag_deductibles', 'deductible_id', 'featurable_id');
    }

    /**
     * Returns a list of taxes that are applicable to this service charge.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function taxes()
    {
        return $this->morphToMany(Constants::TAX_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::TAX_NAMESPACE)->withPivot('scope');
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    //
    // Validation methods
    //

    /**
     * Validate that only one of percentage or amount_money is set.
     *
     * @return void
     * @throws ValidationException
     */
    protected function validateServiceChargeType()
    {
        $hasPercentage = !is_null($this->percentage) && $this->percentage !== 0;
        $hasAmount = !is_null($this->amount_money) && $this->amount_money !== 0;

        if ($hasPercentage && $hasAmount) {
            throw ValidationException::withMessages([
                'service_charge' => 'Service charge cannot have both percentage and amount_money set. Please specify only one.'
            ]);
        }

        if (!$hasPercentage && !$hasAmount) {
            throw ValidationException::withMessages([
                'service_charge' => 'Service charge must have either percentage or amount_money set.'
            ]);
        }
    }

    /**
     * Validate calculation phase constraints according to Square API limitations.
     *
     * @return void
     * @throws ValidationException
     */
    protected function validateCalculationPhaseConstraints()
    {
        $phase = $this->calculation_phase ?? OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE;
        $treatmentType = $this->treatment_type ?? OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT;
        $hasPercentage = !is_null($this->percentage) && $this->percentage !== 0;
        $hasAmount = !is_null($this->amount_money) && $this->amount_money !== 0;

        // Subtotal phase service charge limitations
        if ($phase === OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE) {
            // Note: Cannot validate order line-item level constraint here as it depends on
            // how the service charge is attached to products via pivot tables
            // This validation should be done when attaching to products
        }

        // Total phase service charge limitations
        if ($phase === OrderServiceChargeCalculationPhase::TOTAL_PHASE) {
            // Cannot be taxable
            if ($this->taxable) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Total phase service charges cannot be taxable.'
                ]);
            }
            // Cannot be applied at the order line-item level
            if ($treatmentType === OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Total phase service charges cannot be applied at the product (line-item) level. Use order level instead.'
                ]);
            }
        }

        // Apportioned amount phase service charge limitations
        if ($phase === OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE) {
            // Cannot be used with LINE_ITEM_TREATMENT
            if ($treatmentType === OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Apportioned amount phase cannot be used with line item treatment. Use apportioned treatment instead.'
                ]);
            }

            // Must have amount, not percentage
            if ($hasPercentage && !$hasAmount) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Apportioned amount phase service charges must have a dollar amount, not a percentage.'
                ]);
            }
        }

        // Apportioned percentage phase service charge limitations
        if ($phase === OrderServiceChargeCalculationPhase::APPORTIONED_PERCENTAGE_PHASE) {
            // Cannot be used with LINE_ITEM_TREATMENT
            if ($treatmentType === OrderServiceChargeTreatmentType::LINE_ITEM_TREATMENT) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Apportioned percentage phase cannot be used with line item treatment. Use apportioned treatment instead.'
                ]);
            }

            // Must have percentage, not amount
            if ($hasAmount && !$hasPercentage) {
                throw ValidationException::withMessages([
                    'calculation_phase' => 'Apportioned percentage phase service charges must have a percentage, not a dollar amount.'
                ]);
            }
        }
    }

    /**
     * Validate that a service charge can be applied at the product (line-item) level.
     *
     * @return void
     * @throws ValidationException
     */
    public function validateProductLevelApplication()
    {
        $phase = $this->calculation_phase ?? OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE;

        // Subtotal phase service charges cannot be applied at the order line-item level
        if ($phase === OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE) {
            throw ValidationException::withMessages([
                'scope' => 'Subtotal phase service charges cannot be applied at the product (line-item) level. Use order level instead.'
            ]);
        }

        // Total phase service charges cannot be applied at the order line-item level
        if ($phase === OrderServiceChargeCalculationPhase::TOTAL_PHASE) {
            throw ValidationException::withMessages([
                'scope' => 'Total phase service charges cannot be applied at the product (line-item) level. Use order level instead.'
            ]);
        }
    }

    /**
     * Static method to validate service charge before product attachment.
     *
     * @param ServiceCharge $serviceCharge
     * @return void
     * @throws ValidationException
     */
    public static function validateBeforeProductAttachment(ServiceCharge $serviceCharge)
    {
        $serviceCharge->validateProductLevelApplication();
    }

    /**
     * Check if this service charge can be applied at the product level.
     *
     * @return bool
     */
    public function canBeAppliedToProduct(): bool
    {
        $phase = $this->calculation_phase ?? OrderServiceChargeCalculationPhase::SUBTOTAL_PHASE;

        // Only apportioned phases can be applied to products
        return in_array($phase, [
            OrderServiceChargeCalculationPhase::APPORTIONED_AMOUNT_PHASE,
            OrderServiceChargeCalculationPhase::APPORTIONED_PERCENTAGE_PHASE
        ]);
    }

    /**
     * Check if this service charge can be applied at the order level.
     *
     * @return bool
     */
    public function canBeAppliedToOrder(): bool
    {
        // All phases can be applied at order level, but with different constraints
        return true;
    }
}
