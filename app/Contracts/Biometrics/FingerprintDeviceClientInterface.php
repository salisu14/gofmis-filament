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
     * @param  string  $template  The enrolled template to verify against.
     */
    public function verify(string $template): bool;

    /**
     * Identify a freshly captured fingerprint against a list of stored templates.
     *
     * @param  array  $templates  Array of [id => template]
     * @return string|null The ID of the matched template, or null if no match.
     */
    public function identify(array $templates): ?string;
}
