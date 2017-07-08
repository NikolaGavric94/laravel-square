<?php

namespace Nikolag\Square;

use \Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler;
use SquareConnect\ApiException;

class ExceptionHandler extends Handler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * Report or log an exception.
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if($exception instanceof ApiException)
        {
	        if($exception->getCategory() == Constants::INVALID_REQUEST_ERROR)
	        {
	            if($exception->getCode() == Constants::NOT_FOUND)
	            {
	                $exception = InvalidSquareNonceException($exception->getMessage(), $exception->getCode(), $exception);
	            } 
	            else if($exception->getCode() == Constants::NONCE_USED)
	            {
	                $exception = new UsedSquareNonceException($exception->getMessage(), $exception->getCode(), $exception);
	            }
	        }
	        else if($exception->getCategory() == Constants::PAYMENT_METHOD_ERROR)
	        {
	            if($exception->getCode() == Constants::INVALID_EXPIRATION)
	            {
	                $exception = new InvalidSquareExpirationDateException($exception->getMessage(), $exception->getCode(), $exception);
	            } 
	            else if($exception->getCode() == Constants::VERIFY_POSTAL_CODE)
	            {
	                $exception = new InvalidSquareZipcodeException($exception->getMessage(), $exception->getCode(), $exception);
	            }
	            else if($exception->getCode() == Constants::VERIFY_CVV)
	            {
	                $exception = new InvalidSquareCvvException($exception->getMessage(), $exception->getCode(), $exception);
	            }
	        }
        }

        //Report exception at the end
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        //
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        //
    }
}
