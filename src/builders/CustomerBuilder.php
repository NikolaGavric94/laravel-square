<?php
/**
 * Created by PhpStorm.
 * User: nikola
 * Date: 7/6/18
 * Time: 00:06.
 */

namespace Nikolag\Square\Builders;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Utils\Constants;

class CustomerBuilder
{
    /**
     * @var string
     */
    protected $customerClass = Constants::CUSTOMER_NAMESPACE;

    /**
     * Find or create tax models
     * from taxes array.
     *
     * @param array $data
     *
     * @return \Nikolag\Square\Models\Customer $temp
     * @throws MissingPropertyException
     */
    public function load(array $data)
    {
        /** @var \Nikolag\Square\Models\Customer $temp */$temp = new $this->customerClass;
        //If email doesn't exist on the customer
        //throw new exception because it should exist
        if (! array_key_exists('email', $data) || $data['email'] == null) {
            throw new MissingPropertyException('$email property for object Customer is missing or is null', 500);
        }

        $query = $temp->newQuery()->where('email', $data['email']);

        if ($query->exists()) {
            $temp = $query->first();
        } else {
            $temp->fill($data);
        }

        return $temp;
    }
}
