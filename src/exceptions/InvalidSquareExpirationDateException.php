<?php

namespace Nikolag\Square\Exceptions;

use Exception as PhpException;
use Nikolag\Square\Exception;

class InvalidSquareExpirationDateException extends Exception
{
    public function __construct($message = null, $code = 0, PhpException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
