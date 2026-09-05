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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Application-level 1:N fingerprint identification / beneficiary lookup.
 *
 * Builds an authorisation-aware, active-only, bounded candidate set, decrypts
 * only the templates needed for matching (server-side), and maps a matched
 * fingerprint back to its beneficiary without ever exposing templates or
 * revealing cross-zone beneficiaries.
 *
 * Candidates are sent to the bridge with opaque candidate ids (fingerprint
 * UUIDs) only — no beneficiary PII. The candidate pool is bounded by config and
 * fails safely if it exceeds the limit.
 */
class BiometricIdentificationService
{
    public function __construct(
        private FingerprintDeviceClientInterface $client,
        private BiometricAuditService $audit,
    ) {}

    /**
     * Identify an unknown capture across the authorised active candidate pool.
     *
     * @return array{status: string, beneficiary: Model|null, fingerprint: BeneficiaryFingerprint|null, category: ?string, message: ?string}
     */
    public function identify(User $operator): array
    {
        if (! $operator->can('biometrics.identify')) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                result: 'denied',
                purpose: BiometricPurpose::IDENTIFICATION,
            );

            throw new AccessDeniedHttpException('You are not authorised to perform fingerprint identification.');
        }

        $candidates = $this->authorizedCandidates($operator);

        $max = (int) config('biometrics.identification.max_candidates', 100);
        if (count($candidates) > $max) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'error',
                purpose: BiometricPurpose::IDENTIFICATION,
                reason: 'candidate_limit_exceeded',
            );

            return ['status' => 'error', 'beneficiary' => null, 'fingerprint' => null, 'category' => 'candidate_limit_exceeded', 'message' => 'Identification candidate set is too large.'];
        }

        if (empty($candidates)) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'no_match',
                purpose: BiometricPurpose::IDENTIFICATION,
            );

            return ['status' => 'no_match', 'beneficiary' => null, 'fingerprint' => null, 'category' => null, 'message' => 'No matching beneficiary was found.'];
        }

        try {
            $result = $this->client->identify($candidates);
        } catch (\App\Exceptions\Biometrics\BiometricBridgeException $e) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'error',
                purpose: BiometricPurpose::IDENTIFICATION,
                reason: $e->safeMessage(),
            );

            return ['status' => 'error', 'beneficiary' => null, 'fingerprint' => null, 'category' => 'bridge_error', 'message' => 'Fingerprint scanner is unavailable.'];
        }

        if ($result->isError()) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'error',
                purpose: BiometricPurpose::IDENTIFICATION,
                reason: $result->status,
            );

            return ['status' => 'error', 'beneficiary' => null, 'fingerprint' => null, 'category' => $result->status, 'message' => 'Fingerprint identification could not be completed.'];
        }

        if ($result->isNoMatch()) {
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'no_match',
                purpose: BiometricPurpose::IDENTIFICATION,
            );

            return ['status' => 'no_match', 'beneficiary' => null, 'fingerprint' => null, 'category' => null, 'message' => 'No matching beneficiary was found.'];
        }

        // A match must resolve to an authorised candidate only.
        $matchedFingerprint = BeneficiaryFingerprint::query()
            ->whereKey($result->candidateId)
            ->first();

        if (! $matchedFingerprint || ! $this->candidateInScope($matchedFingerprint, $operator)) {
            // A cross-scope or revoked match must never be reported as success.
            $this->audit->record(
                BiometricOperation::IDENTIFY,
                operator: $operator,
                result: 'error',
                purpose: BiometricPurpose::IDENTIFICATION,
                reason: 'match_out_of_scope',
            );

            return ['status' => 'no_match', 'beneficiary' => null, 'fingerprint' => null, 'category' => null, 'message' => 'No matching beneficiary was found.'];
        }

        // NOTE: last_verified_at is only meaningful for a 1:1 verification MATCH.
        // A 1:N identification match is not treated as proof of a specific
        // enrolled finger, so we deliberately do not bump that timestamp here.

        $beneficiary = $matchedFingerprint->beneficiary;

        $this->audit->record(
            BiometricOperation::IDENTIFY,
            $beneficiary,
            $matchedFingerprint,
            operator: $operator,
            result: 'match',
            purpose: BiometricPurpose::IDENTIFICATION,
            extra: ['confidence' => $result->confidence, 'request_id' => $result->requestId],
        );

        return ['status' => 'match', 'beneficiary' => $beneficiary, 'fingerprint' => $matchedFingerprint, 'category' => null, 'message' => 'Match confirmed.'];
    }

    /**
     * Build the authorised candidate pool: active, non-revoked fingerprints with
     * zone scope applied (Coordinators only see their own zone). Returns array
     * of ['candidate_id' => <opaque>, 'template' => <decrypted>, 'format' => ?].
     */
    protected function authorizedCandidates(User $operator): array
    {
        $query = BeneficiaryFingerprint::query()
            ->where('is_active', true)
            ->whereNull('revoked_at');

        // Zone scoping for coordinators, and user-level permission guard.
        if (! $operator->hasAnyRole(['admin', 'super_admin'])) {
            $zoneId = $operator->coordinatedZone?->id;
            if (! $zoneId) {
                return [];
            }

            $query->whereHasMorph(
                'beneficiary',
                [Widow::class, Orphan::class],
                function ($q) use ($zoneId) {
                    $q->whereHas('deceased', fn ($d) => $d->where('zone_id', $zoneId));
                }
            );
        }

        $candidates = [];
        $totalBytes = 0;
        $maxTotal = (int) config('biometrics.identification.max_total_bytes', 1048576);

        foreach ($query->get() as $fingerprint) {
            $template = $fingerprint->decryptedTemplate();

            if ($template === null || $template === '') {
                continue;
            }

            $templateBytes = strlen($template);
            $totalBytes += $templateBytes;

            if ($totalBytes > $maxTotal) {
                // Abort rather than silently sending an unbounded payload.
                return [];
            }

            $candidates[] = [
                'candidate_id' => (string) $fingerprint->getKey(),
                'template' => $template,
                'format' => $fingerprint->template_format,
            ];
        }

        return $candidates;
    }

    protected function candidateInScope(BeneficiaryFingerprint $fingerprint, User $operator): bool
    {
        if (! $fingerprint->is_active || $fingerprint->revoked_at !== null) {
            return false;
        }

        if ($operator->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        $zoneId = $operator->coordinatedZone?->id;
        if (! $zoneId) {
            return false;
        }

        $beneficiary = $fingerprint->beneficiary;
        if (! $beneficiary instanceof Widow && ! $beneficiary instanceof Orphan) {
            return false;
        }

        $beneficiaryZoneId = $beneficiary->deceased()->withoutGlobalScopes()->value('zone_id');

        return $beneficiaryZoneId === $zoneId;
    }
}
