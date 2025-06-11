<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use Nikolag\Core\Models\Tax as CoreTax;
use Nikolag\Square\Utils\Constants;

class Tax extends CoreTax
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'percentage',
        'amount_money',
        'amount_currency',
        'reference_id',
        'square_catalog_object_id',
        'calculation_phase',
        'inclusion_type',
        'applies_to_custom_amounts',
        'enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'percentage' => 'float',
        'applies_to_custom_amounts' => 'boolean',
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'square_created_at' => 'datetime',
        'square_updated_at' => 'datetime',
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
            $serviceCharge->validateTaxType();
        });

        static::updating(function ($serviceCharge) {
            $serviceCharge->validateTaxType();
        });
    }

    //
    // Accessors and Mutators
    //

    /**
     * Set the percentage attribute.
     *
     * @param mixed $value
     * @return void
     */
    public function setPercentageAttribute($value)
    {
        if (!is_null($this->amount_money) && !is_null($value)) {
            throw ValidationException::withMessages([
                'tax' => 'Tax cannot have percentage while amount_money is set.'
            ]);
        }

        $this->attributes['percentage'] = $value;
    }

    /**
     * Set the amount_money attribute.
     *
     * @param mixed $value
     * @return void
     */
    public function setAmountMoneyAttribute($value)
    {
        if (!is_null($this->amount_money) && !is_null($value)) {
            throw ValidationException::withMessages([
                'tax' => 'Tax cannot have amount_money while percentage is set.'
            ]);
        }

        $this->attributes['amount_money'] = $value;
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
    protected function validateTaxType()
    {
        $hasPercentage = !is_null($this->percentage) && $this->percentage !== 0;
        $hasAmount = !is_null($this->amount_money) && $this->amount_money !== 0;

        if ($hasPercentage && $hasAmount) {
            throw ValidationException::withMessages([
                'service_charge' => 'Tax cannot have both percentage and amount_money set. Please specify only one.'
            ]);
        }

        if (!$hasPercentage && !$hasAmount) {
            throw ValidationException::withMessages([
                'service_charge' => 'Tax must have either percentage or amount_money set.'
            ]);
        }
    }

    //
    // Relationships
    //

    /**
     * Return a list of orders which use this tax.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function orders()
    {
        return $this->morphToMany(config('nikolag.connections.square.order.namespace'), 'deductible', 'nikolag_deductibles', 'deductible_id', 'featurable_id');
    }

    /**
     * Return a list of products which use this tax.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function products()
    {
        return $this->morphToMany(Constants::ORDER_PRODUCT_NAMESPACE, 'deductible', 'nikolag_deductibles', 'deductible_id', 'featurable_id');
    }

    //
    // Serialization
    //

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
}
