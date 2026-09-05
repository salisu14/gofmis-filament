<?php

namespace App\Exceptions\Biometrics;

/**
 * The bridge rejected the request (authentication failure) or returned a
 * response the application cannot trust or map to a fingerprint template.
 */
class InvalidBridgeResponseException extends BiometricBridgeException
{
    public function __construct(string $message = 'Fingerprint enrollment failed. Please try again.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
