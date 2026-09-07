<?php

namespace App\Services\Biometrics;

use App\Enums\BiometricOperation;
use App\Enums\BiometricPurpose;
use App\Models\BeneficiaryFingerprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Biometric governance / audit service.
 *
 * Writes structured, append-only biometric operation events into the existing
 * application activity ledger (Spatie Activitylog) under a dedicated
 * "biometric" log name. This reuses the repository's immutable audit
 * infrastructure instead of introducing a parallel ledger.
 *
 * Security invariants:
 *  - Never stores a template, encrypted template, raw image, bridge token, or
 *    encryption key in the audit record.
 *  - Captures only operational metadata (operation, purpose, beneficiary,
 *    fingerprint reference, operator, result, correlation id, source).
 *  - The event is append-only: ordinary application paths cannot edit or delete
 *    activity rows.
 */
class BiometricAuditService
{
    public const LOG_NAME = 'biometric';

    /**
     * Record a biometric operation.
     *
     * @param  BiometricOperation  $operation  canonical operation type
     * @param  Model|null  $beneficiary  the Widow or Orphan (null for anonymous
     *                                   outcomes such as a no-match identification)
     * @param  BeneficiaryFingerprint|null  $fingerprint  resulting/affected fingerprint (optional)
     * @param  User|null  $operator  the acting user (defaults to auth()->user())
     * @param  string  $result  outcome category (success | failed | revoked | match | ...)
     * @param  BiometricPurpose|null  $purpose  canonical purpose
     * @param  string|null  $reason  safe reason / status category (never template material)
     * @param  array  $extra  additional safe metadata (request_id, source, ...)
     */
    public function record(
        BiometricOperation $operation,
        ?Model $beneficiary = null,
        ?BeneficiaryFingerprint $fingerprint = null,
        ?User $operator = null,
        string $result = 'success',
        ?BiometricPurpose $purpose = null,
        ?string $reason = null,
        array $extra = [],
    ): void {
        $operator = $operator ?: auth()->user();

        $properties = array_merge([
            'biometric_operation' => $operation->value,
            'purpose' => ($purpose ?? $this->defaultPurposeFor($operation))->value,
            'beneficiary_type' => $beneficiary ? $beneficiary->getMorphClass() : null,
            'beneficiary_id' => $beneficiary ? (string) $beneficiary->getKey() : null,
            'fingerprint_id' => $fingerprint ? (string) $fingerprint->getKey() : null,
            'operator_id' => $operator ? (string) $operator->getKey() : null,
            'result' => $result,
            'reason' => $reason,
            'lawful_basis_reference' => config('biometrics.governance.lawful_basis_reference'),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ], $extra);

        // Deterministic correlation id when the caller has not supplied one.
        $properties['request_id'] ??= (string) Str::uuid();

        $activity = activity(self::LOG_NAME)
            ->event($operation->value)
            ->withProperties($properties);

        if ($operator) {
            $activity->causedBy($operator);
        }

        if ($fingerprint) {
            $activity->performedOn($fingerprint);
        } elseif ($beneficiary) {
            $activity->performedOn($beneficiary);
        }

        $label = $beneficiary
            ? sprintf('%s %s', $beneficiary->getMorphClass(), $beneficiary->getKey())
            : 'anonymous';

        $activity->log(sprintf(
            'Biometric %s (%s) for %s',
            $operation->getLabel(),
            $result,
            $label
        ));
    }

    /**
     * Sensible default purpose for an operation when the caller has not
     * provided an explicit one.
     */
    protected function defaultPurposeFor(BiometricOperation $operation): BiometricPurpose
    {
        return match ($operation) {
            BiometricOperation::ENROLLMENT => BiometricPurpose::ENROLLMENT,
            BiometricOperation::REVOCATION => BiometricPurpose::ADMINISTRATIVE_CORRECTION,
            BiometricOperation::VERIFY => BiometricPurpose::IDENTITY_VERIFICATION,
            BiometricOperation::IDENTIFY => BiometricPurpose::IDENTIFICATION,
            BiometricOperation::CORRECTION => BiometricPurpose::ADMINISTRATIVE_CORRECTION,
        };
    }
}
