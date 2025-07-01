<?php

namespace Nikolag\Square\Exceptions;

use Nikolag\Core\Exceptions\Exception;

class InvalidSquareSignatureException extends Exception
{
    /**
     * @var string
     */
    protected $message = 'Square webhook signature is invalid or missing.';
}