<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ModifierOptionLocationPivot extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_modifier_option_location';
}
