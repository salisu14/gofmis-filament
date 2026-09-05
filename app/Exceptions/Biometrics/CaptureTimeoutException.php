<?php

namespace App\Exceptions\Biometrics;

/**
 * The bridge responded, but the action timed out (e.g. the user did not
 * present a finger within the expected window).
 */
class CaptureTimeoutException extends BiometricBridgeException
{
    public function __construct(string $message = 'Fingerprint capture timed out. Please try again.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
