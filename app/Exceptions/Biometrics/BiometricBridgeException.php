<?php

namespace App\Exceptions\Biometrics;

use RuntimeException;

/**
 * Base exception for biometric device/bridge failures.
 *
 * Intentionally separate from the generic application exception handling so
 * Filament/domain code can catch biometric-specific failures without leaking
 * HTTP bodies, stack traces, tokens, or template material to end users.
 */
class BiometricBridgeException extends RuntimeException
{
    /**
     * A safe, user-facing message that must never contain tokens, templates,
     * or internal bridge details.
     */
    public function safeMessage(): string
    {
        return $this->getMessage();
    }
}
