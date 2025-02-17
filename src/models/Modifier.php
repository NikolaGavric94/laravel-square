<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modifier extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_modifiers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'internal_name',
        'square_catalog_object_id',
        'ordinal',
        'selection_type',
        'modifier_type',
        'max_length',
        'is_text_required',
    ];

    /**
     * Return a list of products which are included in this order.
     *
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Constants::PRODUCT_NAMESPACE, 'nikolag_product_order', 'order_id', 'product_id')
            ->using(Constants::ORDER_PRODUCT_NAMESPACE)->withPivot('quantity', 'id');
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
