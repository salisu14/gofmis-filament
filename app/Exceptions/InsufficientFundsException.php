<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected $message = 'Insufficient fund to perform this operation.';
}
