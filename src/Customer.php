<?php

namespace Nikolag\Square;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
	/**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "nikolag_customers";

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
    	'firstName',
    	'lastName',
    	'companyName',
    	'nickname',
    	'email',
    	'nickname',
    	'phone'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
    	'id',
    	'squareId',
    	'referenceId',
    	'referenceType'
    ];
}
