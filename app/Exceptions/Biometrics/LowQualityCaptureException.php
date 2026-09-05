<?php

namespace App\Exceptions\Biometrics;

/**
 * Raised when a freshly captured fingerprint is of too low quality to enroll.
 * Safe user-facing message only; never includes template or bridge details.
 */
class LowQualityCaptureException extends BiometricBridgeException
{
    public function __construct(string $message = 'Fingerprint quality is too low. Clean the scanner and try again.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
