<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModifierOption extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_modifier_options';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'selection_type',
        'square_catalog_object_id',
        'modifier_id',
        'price_money_amount',
        'price_money_currency',
    ];

    /**
     * Location override relationship.
     *
     * @return HasMany
     */
    public function locationOverrides(): HasMany
    {
        return $this->hasMany(ModifierOptionLocationPivot::class, 'id', 'nikolag_modifier_option_id');
    }

    /**
     * Parent modifier relationship.
     *
     * @return BelongsTo
     */
    public function parentModifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
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
}
