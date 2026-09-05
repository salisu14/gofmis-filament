<?php

namespace App\Exceptions\Biometrics;

/**
 * The biometric bridge process could not be reached (connection failure,
 * DNS, refused connection, or bridge down).
 */
class BridgeUnavailableException extends BiometricBridgeException
{
    public function __construct(string $message = 'Fingerprint scanner is unavailable.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
