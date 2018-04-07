<?php

namespace Nikolag\Square\Exceptions;

use Nikolag\Square\Exception;
use \Exception as PhpException;

class InvalidSquareOrderException extends Exception
{
    public function __construct($message = null, $code = 0, PhpException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}