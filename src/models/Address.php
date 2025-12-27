<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Square\Models\Address as SquareAddress;
use Square\Models\Builders\AddressBuilder;

class Address extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_addresses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'locality',
        'administrative_district_level_1',
        'administrative_district_level_2',
        'administrative_district_level_3',
        'sublocality',
        'sublocality_2',
        'sublocality_3',
        'postal_code',
        'country',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'addressable_type',
        'addressable_id',
    ];

    /**
     * Get the parent addressable model (Customer, Recipient, etc.).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convert address fields to Square Address object.
     *
     * @return SquareAddress
     */
    public function toSquareAddress(): SquareAddress
    {
        return AddressBuilder::init()
        ->addressLine1($this->address_line_1)
        ->addressLine2($this->address_line_2)
        ->addressLine3($this->address_line_3)
        ->locality($this->locality)
        ->administrativeDistrictLevel1($this->administrative_district_level_1)
        ->administrativeDistrictLevel2($this->administrative_district_level_2)
        ->administrativeDistrictLevel3($this->administrative_district_level_3)
        ->sublocality($this->sublocality)
        ->sublocality2($this->sublocality_2)
        ->sublocality3($this->sublocality_3)
        ->postalCode($this->postal_code)
        ->country($this->country)
        ->build();
    }

    /**
     * Create an Address model from Square Address object.
     *
     * @param  SquareAddress  $squareAddress
     * @return static
     */
    public static function fromSquareAddress(SquareAddress $squareAddress): static
    {
        return new static([
            'address_line_1' => $squareAddress->getAddressLine1(),
            'address_line_2' => $squareAddress->getAddressLine2(),
            'address_line_3' => $squareAddress->getAddressLine3(),
            'locality' => $squareAddress->getLocality(),
            'administrative_district_level_1' => $squareAddress->getAdministrativeDistrictLevel1(),
            'administrative_district_level_2' => $squareAddress->getAdministrativeDistrictLevel2(),
            'administrative_district_level_3' => $squareAddress->getAdministrativeDistrictLevel3(),
            'sublocality' => $squareAddress->getSublocality(),
            'sublocality_2' => $squareAddress->getSublocality2(),
            'sublocality_3' => $squareAddress->getSublocality3(),
            'postal_code' => $squareAddress->getPostalCode(),
            'country' => $squareAddress->getCountry(),
        ]);
    }

    /**
     * Update address fields from Square Address object.
     *
     * @param  \Square\Models\Address  $squareAddress
     * @return void
     */
    public function updateFromSquareAddress(SquareAddress $squareAddress): void
    {
        $this->fill([
            'address_line_1' => $squareAddress->getAddressLine1(),
            'address_line_2' => $squareAddress->getAddressLine2(),
            'address_line_3' => $squareAddress->getAddressLine3(),
            'locality' => $squareAddress->getLocality(),
            'administrative_district_level_1' => $squareAddress->getAdministrativeDistrictLevel1(),
            'administrative_district_level_2' => $squareAddress->getAdministrativeDistrictLevel2(),
            'administrative_district_level_3' => $squareAddress->getAdministrativeDistrictLevel3(),
            'sublocality' => $squareAddress->getSublocality(),
            'sublocality_2' => $squareAddress->getSublocality2(),
            'sublocality_3' => $squareAddress->getSublocality3(),
            'postal_code' => $squareAddress->getPostalCode(),
            'country' => $squareAddress->getCountry(),
        ]);
    }
}
