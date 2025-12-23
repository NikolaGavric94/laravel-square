<?php

namespace Nikolag\Square;

use Exception as PhpException;
use Illuminate\Queue\SerializesModels;
use Nikolag\Core\Exceptions\Exception as BaseException;

class Exception extends BaseException
{
    use SerializesModels;

    /**
     * @var Exception[]
     */
    protected array $additionalExceptions = [];

    /**
     * Constructor.
     *
     * @param  mixed  $message
     * @param  mixed  $code
     * @param  PhpException|null  $previous
     */
    public function __construct($message = null, $code = 0, ?PhpException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Exception[]
     */
    public function getAdditionalExceptions(): array
    {
        return $this->additionalExceptions;
    }

    /**
     * @param  Exception[]  $additionalExceptions
     * @return $this
     */
    public function setAdditionalExceptions(array $additionalExceptions): Exception
    {
        $this->additionalExceptions = $additionalExceptions;

        return $this;
    }

    /**
     * Add more errors in case there are multiple issues.
     *
     * @param  Exception  $exception
     * @return $this
     */
    public function addAdditionalException(Exception $exception): Exception
    {
        $this->additionalExceptions[] = $exception;

        return $this;
    }
}
