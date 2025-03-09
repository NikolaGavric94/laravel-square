<?php

namespace Nikolag\Square\Builders;

class Validate
{
    /**
     * Validates that the required fields are present in the data array.
     *
     * @param array $data
     * @param array $requiredFields
     *
     * @throws MissingPropertyException
     *
     * @return void
     */
    public static function validateRequiredFields(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || empty($data[$field])) {
                throw new MissingPropertyException("The $field field is required", 500);
            }
        }
    }
}
