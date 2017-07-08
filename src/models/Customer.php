<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\SquareCustomer;
use Nikolag\Square\Utils\Constants;

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
    	'first_name',
    	'last_name',
    	'company_name',
    	'nickname',
    	'email',
    	'nickname',
    	'phone',
        'note'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
    	'id',
    	'square_id',
    	'reference_id',
    	'reference_type'
    ];

    /**
     * List of users this customer bought from.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function merchants()
    {
        return $this->belongsToMany(config('nikolag.user.namespace'), 'nikolag_customer_user', 'reference_id', 'customer_id');
    }

    /**
     * Charge customer.
     * 
     * @param float $amount 
     * @param string $card_nonce 
     * @param string $location_id 
     * @return \Nikolag\Square\Model\Transaction
     */
    public function charge(float $amount, string $card_nonce, string $location_id)
    {
        $customer = new SquareCustomer($this->attributes);
        //TO-DO
        return $customer->charge($amount, $card_nonce, $location_id);
    }

    /**
     * List of previous transactions.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Constants::TRANSACTION_NAMESPACE, 'customer_id', Constants::CUSTOMER_IDENTIFIER);
    }
}
