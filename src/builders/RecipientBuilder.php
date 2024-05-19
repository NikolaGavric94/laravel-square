<?php

namespace Nikolag\Square\Builders;

use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Utils\Constants;

class RecipientBuilder
{
    /**
     * @var string
     */
    protected string $recipientClass = Constants::RECIPIENT_NAMESPACE;

    /**
     * Find or create tax models
     * from taxes array.
     *
     * @param  array  $data
     * @return Recipient $temp
     *
     * @throws MissingPropertyException
     */
    public function load(array $recipientData): Recipient
    {
        $temp = new $this->recipientClass();

        // Check to make sure a recipient either has customer_id, or all of the individual fields
        $individualFields    = [
            'display_name',
            'email_address',
            'phone_number',
            'address',
        ];
        $hasCustomerID       = Arr::has($recipientData, 'customer_id') && $recipientData['customer_id'] != null;
        $hasIndividualFields = collect($individualFields)->every(function ($field) use ($recipientData) {
            return Arr::has($recipientData, $field) && $recipientData[$field] != null;
        });
        if (
            !$hasCustomerID
            && !$hasIndividualFields
        ) {
            throw new MissingPropertyException('Recipient must have customer_id or all other fields', 500);
        }

        $query = $temp->newQuery()->where('email_address', $recipientData['email_address']);

        if ($query->exists()) {
            $temp = $query->first();
        } else {
            $temp->fill($recipientData);
        }

        return $temp;
    }
}
