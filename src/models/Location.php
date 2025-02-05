<?php

namespace Nikolag\Square\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Square\Models\Location as SquareLocation;

class Location extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_locations';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'capabilities' => 'array',
        'coordinates' => 'json',
        'square_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Processes the location data during sync
     *
     * @param array $locationData The json serialized location data from the REST API.
     *
     * @var array
     */
    public static function processLocationData(SquareLocation $location): array
    {
        $locationData = $location->jsonSerialize();

        // Remove the ID and set it as the square_id
        $locationData['square_id'] = $locationData['id'];
        unset($locationData['id']);

        // Update columns that are stores as more complex objects
        $locationData['address'] = json_encode($location->getAddress()?->jsonSerialize());
        $locationData['capabilities'] = json_encode($location->getCapabilities());
        $locationData['business_hours'] = json_encode($location->getBusinessHours()?->jsonSerialize());

        // Cast any dates from the model
        $emptyModel = new self();
        foreach ($emptyModel->casts as $key => $value) {
            if ($value == 'datetime' && isset($locationData[$key])) {
                $value = new DateTime($locationData[$key]);
                $locationData[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $locationData;
    }
}
