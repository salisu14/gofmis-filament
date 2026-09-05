<?php

namespace App\Contracts\Biometrics;

interface FingerprintDeviceClientInterface
{
    /**
     * Check the health of the biometric scanner/bridge.
     *
     * @return array ['status' => 'ok'|'error', 'message' => string]
     */
    public function health(): array;

    /**
     * Capture a fingerprint.
     *
     * @return array ['status' => 'ok'|'error', 'template' => string|null, 'quality' => int|null, 'message' => string]
     */
    public function capture(): array;

    /**
     * Enroll a fingerprint (typically multiple captures to form a solid template).
     *
     * @return array [
     *               'status' => 'ok'|'error',
     *               'template' => string|null,
     *               'quality' => int|null,
     *               'format' => string|null,
     *               'device_manufacturer' => string|null,
     *               'device_model' => string|null,
     *               'device_serial' => string|null,
     *               'sdk_version' => string|null,
     *               'message' => string
     *               ]
     */
    public function enroll(): array;

    /**
     * Verify a freshly captured fingerprint against a stored template.
     *
     * Returns an explicit FingerprintVerificationResult so callers can
     * distinguish MATCH / NO_MATCH / ERROR instead of collapsing scanner
     * failures or low-quality captures into a boolean "false".
     *
     * @param  string  $template  The enrolled reference template (plaintext, server-side only).
     * @param  string|null  $templateFormat  Template format for compatibility.
     */
    public function verify(string $template, ?string $templateFormat = null): \App\Services\Biometrics\FingerprintVerificationResult;

    /**
     * Identify a freshly captured fingerprint against an authorised candidate
     * list. Candidates use opaque correlation ids; beneficiary PII is never sent.
     *
     * @param  array  $candidates  Array of ['candidate_id' => string, 'template' => string, 'format' => string|null]
     */
    public function identify(array $candidates): \App\Services\Biometrics\FingerprintIdentificationResult;
}
