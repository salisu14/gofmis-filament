<?php

namespace App\Exceptions\Biometrics;

/**
 * The scanner device is disconnected, busy, has a cancelled capture, or the
 * bridge reported an error state.
 */
class ScannerOperationException extends BiometricBridgeException
{
    public function __construct(string $message = 'Fingerprint scanner is unavailable.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
