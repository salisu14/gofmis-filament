<?php

namespace App\Enums;

/**
 * Canonical biometric operation types recorded in the biometric audit ledger.
 *
 * These values are intentionally stable so future verification/identification
 * workflows can reuse the same audit mechanism without a schema change.
 */
enum BiometricOperation: string
{
    case ENROLLMENT = 'enrollment';

    case REVOCATION = 'revocation';

    case VERIFY = 'verify';

    case IDENTIFY = 'identify';

    case CORRECTION = 'correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::ENROLLMENT => 'Enrollment',
            self::REVOCATION => 'Revocation',
            self::VERIFY => 'Verification',
            self::IDENTIFY => 'Identification',
            self::CORRECTION => 'Correction',
        };
    }
}
