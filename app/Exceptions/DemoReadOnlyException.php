<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class DemoReadOnlyException extends HttpException
{
    public function __construct(string $message = 'Demo Observer account is read-only. Persistent system changes are strictly prohibited.')
    {
        parent::__construct(403, $message);
    }
}
