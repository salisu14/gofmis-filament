<?php

namespace App\Enums;

use BackedEnum;

/**
 * Canonical biometric purposes (governance/authorisation semantics).
 *
 * The lawful/authorisation basis is deliberately expressed as a reference value
 * (e.g. a policy or legal-basis identifier) rather than hard-coding "consent".
 * The Foundation's legal/privacy governance process must approve the exact basis
 * referenced for real deployments.
 */
enum BiometricPurpose: string
{
    case ENROLLMENT = 'enrollment';

    case IDENTITY_VERIFICATION = 'identity_verification';

    case IDENTIFICATION = 'identification';

    case ADMINISTRATIVE_CORRECTION = 'administrative_correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::ENROLLMENT => 'Fingerprint enrollment',
            self::IDENTITY_VERIFICATION => 'Identity verification',
            self::IDENTIFICATION => 'Beneficiary identification',
            self::ADMINISTRATIVE_CORRECTION => 'Administrative correction',
        };
    }

    /**
     * Convert a plain string to a purpose when possible; fall back to null.
     */
    public static function tryFromAny(string|BackedEnum|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return $value === null ? null : self::tryFrom((string) $value);
    }
}
