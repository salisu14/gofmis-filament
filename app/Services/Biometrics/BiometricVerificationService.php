<?php

namespace App\Services\Biometrics;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
use App\Enums\BiometricOperation;
use App\Enums\BiometricPurpose;
use App\Models\BeneficiaryFingerprint;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Application-level 1:1 fingerprint verification.
 *
 * Flow: known beneficiary + chosen active fingerprint -> live capture ->
 * server-side template decryption -> device-client verify -> MATCH / NO_MATCH /
 * ERROR -> audit -> last_verified_at update on MATCH.
 *
 * Authorization is enforced server-side: Admin/Super Admin may verify any
 * accessible beneficiary; a Coordinator may only verify a beneficiary within
 * their managed zone and only if the narrow verify permission is granted.
 * Template material is decrypted only here and never written to UI state.
 */
class BiometricVerificationService
{
    public function __construct(
        private FingerprintDeviceClientInterface $client,
        private BiometricAuditService $audit,
    ) {}

    /**
     * Verify a known beneficiary against an active enrolled fingerprint.
     *
     * @return array ['status' => 'match'|'no_match'|'error'|'denied', 'category' => ?string, 'message' => ?string]
     */
    public function verify(Model $beneficiary, BeneficiaryFingerprint $fingerprint, User $operator): array
    {
        if (! $this->canVerify($beneficiary, $operator)) {
            $this->audit->record(
                BiometricOperation::VERIFY,
                $beneficiary,
                $fingerprint,
                operator: $operator,
                result: 'denied',
                purpose: BiometricPurpose::IDENTITY_VERIFICATION,
            );

            throw new AccessDeniedHttpException('You are not authorised to verify this beneficiary.');
        }

        if (! $fingerprint->is_active || $fingerprint->revoked_at !== null) {
            return $this->recordFailure($beneficiary, $fingerprint, $operator, 'revoked_or_inactive');
        }

        try {
            $template = $fingerprint->decryptedTemplate();

            if ($template === null || $template === '') {
                return $this->recordFailure($beneficiary, $fingerprint, $operator, 'empty_template');
            }

            // Live capture + match happen at the device boundary; the result is
            // an explicit MATCH / NO_MATCH / ERROR.
            $result = $this->client->verify($template, $fingerprint->template_format);

            if ($result->isError()) {
                return $this->recordFailure($beneficiary, $fingerprint, $operator, $result->status);
            }

            if ($result->isNoMatch()) {
                $this->audit->record(
                    BiometricOperation::VERIFY,
                    $beneficiary,
                    $fingerprint,
                    operator: $operator,
                    result: 'no_match',
                    purpose: BiometricPurpose::IDENTITY_VERIFICATION,
                    extra: ['confidence' => $result->confidence, 'request_id' => $result->requestId],
                );

                return ['status' => 'no_match', 'category' => null, 'message' => 'No match.'];
            }

            // MATCH: update last_verified_at (once), then audit.
            DB::transaction(function () use ($fingerprint) {
                $fingerprint->update(['last_verified_at' => now()]);
            });

            $this->audit->record(
                BiometricOperation::VERIFY,
                $beneficiary,
                $fingerprint,
                operator: $operator,
                result: 'match',
                purpose: BiometricPurpose::IDENTITY_VERIFICATION,
                extra: ['confidence' => $result->confidence, 'request_id' => $result->requestId],
            );

            return ['status' => 'match', 'category' => null, 'message' => 'Match confirmed.'];
        } catch (\App\Exceptions\Biometrics\BiometricBridgeException $e) {
            return $this->recordFailure($beneficiary, $fingerprint, $operator, $e->safeMessage());
        }
    }

    /**
     * Whether the operator may verify the given beneficiary.
     */
    public function canVerify(Model $beneficiary, User $operator): bool
    {
        if ($operator->hasAnyRole(['admin', 'super_admin'])) {
            return $operator->can('biometrics.verify');
        }

        if ($operator->isCoordinator()) {
            // Narrow Coordinator support is only permitted when both the verify
            // permission AND zone ownership hold; never ever bypass.
            if (! $operator->can('biometrics.verify')) {
                return false;
            }

            return $this->beneficiaryZoneId($beneficiary) !== null
                && $operator->managesZone($this->beneficiaryZoneId($beneficiary));
        }

        return false;
    }

    protected function recordFailure(Model $beneficiary, BeneficiaryFingerprint $fingerprint, User $operator, string $category): array
    {
        $safeMessage = match ($category) {
            'revoked_or_inactive' => 'This fingerprint has been revoked and cannot be verified.',
            'empty_template' => 'This fingerprint has no readable template.',
            'scanner_unavailable', 'scanner' => 'Fingerprint scanner is unavailable.',
            'timeout' => 'Fingerprint verification timed out. Please try again.',
            'low_quality' => 'Fingerprint quality is too low. Clean the scanner and try again.',
            'malformed_response' => 'Fingerprint verification could not be completed.',
            default => 'Fingerprint verification could not be completed.',
        };

        $this->audit->record(
            BiometricOperation::VERIFY,
            $beneficiary,
            $fingerprint,
            operator: $operator,
            result: 'error',
            purpose: BiometricPurpose::IDENTITY_VERIFICATION,
            reason: $category,
        );

        return ['status' => 'error', 'category' => $category, 'message' => $safeMessage];
    }

    protected function beneficiaryZoneId(Model $beneficiary): ?string
    {
        if ($beneficiary instanceof Widow || $beneficiary instanceof Orphan) {
            return $beneficiary->deceased()->withoutGlobalScopes()->value('zone_id');
        }

        return $beneficiary->deceased?->zone_id ?? $beneficiary->zone_id ?? null;
    }
}
