<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Model;
use Square\Models\Address;

class Recipient extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_recipients';

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
        'square_customer_id',
        'display_name',
        'email_address',
        'phone_number',
        'address',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'address' => 'array',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Parses the address and returns it as a Square Address model.
     *
     * @return Address
     */
    public function getSquareRequestAddress(): Address
    {
        $address = new Address();
        $address->setAddressLine1($this->address['address_line_1'] ?? null);
        $address->setAddressLine2($this->address['address_line_2'] ?? null);
        $address->setAddressLine3($this->address['address_line_3'] ?? null);
        $address->setLocality($this->address['locality'] ?? null);
        $address->setSublocality($this->address['sublocality'] ?? null);
        $address->setSublocality2($this->address['sublocality_2'] ?? null);
        $address->setSublocality3($this->address['sublocality_3'] ?? null);
        $address->setAdministrativeDistrictLevel1($this->address['administrative_district_level_1'] ?? null);
        $address->setAdministrativeDistrictLevel2($this->address['administrative_district_level_2'] ?? null);
        $address->setAdministrativeDistrictLevel3($this->address['administrative_district_level_3'] ?? null);
        $address->setPostalCode($this->address['postal_code'] ?? null);
        $address->setCountry($this->address['country'] ?? null);
        $address->setFirstName($this->address['first_name'] ?? null);
        $address->setLastName($this->address['last_name'] ?? null);

        return $address;
    }
}
