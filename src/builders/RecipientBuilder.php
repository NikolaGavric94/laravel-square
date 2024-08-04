<?php

namespace Nikolag\Square\Builders;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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
     * Find or create a recipient.
     *
     * @param  array  $data
     * @return Recipient $temp
     *
     * @throws MissingPropertyException
     */
    public function load(array $recipientData): Recipient
    {
        $temp = new $this->recipientClass();

        $query = $temp->newQuery();

        // Build the query
        if (array_key_exists('customer_id', $recipientData)) {
            $query->where('customer_id', $recipientData['customer_id']);
        } else {
            $query->where('email_address', $recipientData['email_address']);
        }

        if ($query->exists()) {
            $temp = $query->first();
        } else {
            // Make sure the data is valid
            $this->validate($recipientData);
            $temp->fill($recipientData);
        }

        return $temp;
    }

    /**
     * Validate the recipient data.
     *
     * @param  array  $data
     * @return bool
     *
     * @throws ValidationException
     */
    public function validate(array $recipientData): bool
    {
        $recipient = new $this->recipientClass();
        $recipient->fill($recipientData);

        $individualFieldsRules = [
            'display_name' => 'required',
            'email_address' => 'required',
            'phone_number' => 'required',
            'address' => 'required',
        ];

        // If the recipient has a customer_id, we don't need the individual fields
        $hasCustomerID = Arr::has($recipientData, 'customer_id') && $recipientData['customer_id'] != null;

        if (!$hasCustomerID) {
            Validator::make($recipient->toArray(), $individualFieldsRules)->validate();
        } else {
            // As long as the customer_id is present, we're good - Square requires either customer_id or the details
            // cf. https://github.com/square/square-php-sdk/blob/master/doc/models/fulfillment-recipient.md
        }

        return true;
    }
}
