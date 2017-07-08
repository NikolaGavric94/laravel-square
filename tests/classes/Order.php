<?php

namespace Nikolag\Square\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
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
        'status', 'guests_count', 'total', 'order_date'
    ];

    public function location() {
    	return $this->belongsTo('App\Location');
    }

    /**
     * Get the addons for the location.
     */
    public function package() {
        return $this->hasOne('App\Package');
    }

    /**
     * All addons for this order.
     */
    public function addons() {
    	return $this->hasMany('App\Addon');
    }
}