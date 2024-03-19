<?php

namespace Nikolag\Square\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Fulfillment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_fulfillments';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'state',
        'type'
    ];
}
