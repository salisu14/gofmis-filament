<?php

namespace App\Exceptions;

use Exception;

class InsufficientBankBalanceException extends Exception
{
    protected $message = 'Insufficient bank balance to perform this operation.';
}
