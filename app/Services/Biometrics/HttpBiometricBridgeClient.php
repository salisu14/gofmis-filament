<?php

namespace App\Services\Biometrics;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
use App\Exceptions\Biometrics\BiometricBridgeException;
use App\Exceptions\Biometrics\BridgeUnavailableException;
use App\Exceptions\Biometrics\CaptureTimeoutException;
use App\Exceptions\Biometrics\InvalidBridgeResponseException;
use App\Exceptions\Biometrics\LowQualityCaptureException;
use App\Exceptions\Biometrics\ScannerOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * HTTP client for a LOCAL/TRUSTED fingerprint scanner bridge.
 *
 * GOFMIS (Filament/domain) never talks to vendor SDKs or USB/WebUSB directly.
 * It depends only on FingerprintDeviceClientInterface. This implementation
 * translates capture/enroll into versioned HTTP calls to the configured bridge.
 *
 * The base URL + auth token come exclusively from server config. They are never
 * derived from request input, Livewire, or beneficiary fields (no SSRF
 * surface, no user-selectable scanner URL).
 *
 * The bridge is an integration boundary: we send the minimum data the scanner
 * needs (finger position + correlation id) and never transmit beneficiary PII.
 *
 * Security invariants:
 *  - PII is never sent.
 *  - The nameless template is validated before persistence.
 *  - Oversized payloads are rejected.
 *  - Timeouts are bounded and configurable.
 *  - Templates, encrypted templates, tokens and keys are never logged.
 */
class HttpBiometricBridgeClient implements FingerprintDeviceClientInterface
{
    /**
     * Versioned bridge API prefix.
     */
    protected string $apiPrefix = '/api/v1';

    protected function bridgeConfig(): array
    {
        return config('biometrics.bridge', []);
    }

    protected function baseUrl(): string
    {
        return (string) ($this->bridgeConfig()['base_url'] ?? '');
    }

    /**
     * Build the HTTP client for the trusted bridge with bounded timeouts and,
     * when configured, an isolated bearer token.
     */
    protected function http()
    {
        $config = $this->bridgeConfig();

        $client = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($config['timeout'] ?? 30))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 3));

        if (! empty($config['token'])) {
            $client->withToken((string) $config['token']);
        }

        return $client;
    }

    /**
     * Check the health/connectivity of the bridge. Suitable for diagnostics;
     * intentionally kept internal to this client (does not broaden the domain
     * interface for every implementation).
     */
    public function health(): array
    {
        try {
            $response = $this->http()->get($this->apiPrefix.'/health');

            if ($response->successful()) {
                $payload = $response->json();

                return [
                    'status' => isset($payload['status']) && $payload['status'] === 'ok' ? 'ok' : 'error',
                    'message' => $payload['message'] ?? 'Biometric bridge is healthy.',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Biometric bridge is unavailable.',
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => 'error',
                'message' => 'Biometric bridge is unavailable.',
            ];
        }
    }

    /**
     * Capture a fingerprint from the bridge and return the validated result.
     * Used by the same UI flow as enroll().
     */
    public function capture(?string $fingerPosition = null): array
    {
        return $this->performCapture($fingerPosition)->toArray();
    }

    /**
     * Enroll a fingerprint via the bridge (typically a stronger template).
     */
    public function enroll(): array
    {
        return $this->performCapture()->toArray();
    }

    /**
     * Application-level 1:1 verification is owned by PB-NEXT-02D. This client
     * fails loudly rather than silently claiming a match without a real bridge
     * contract.
     */
    public function verify(string $template, ?string $templateFormat = null): FingerprintVerificationResult
    {
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
            'reference_template' => $template,
        ];

        if ($templateFormat !== null) {
            $payload['template_format'] = $templateFormat;
        }

        $response = $this->dispatch('/api/v1/fingerprints/verify', $payload, $requestId);

        return $this->mapVerificationResponse($response, $requestId);
    }

    public function identify(array $candidates): FingerprintIdentificationResult
    {
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
            'candidates' => array_values($candidates),
        ];

        $response = $this->dispatch('/api/v1/fingerprints/identify', $payload, $requestId);

        return $this->mapIdentificationResponse($response, $requestId);
    }

    /**
     * Perform a POST to a bridge endpoint and return the decoded JSON array.
     * No automatic retry is performed: a capture/verify/identify POST is not
     * idempotent and a retry could re-trigger a live capture.
     *
     * @throws BiometricBridgeException
     */
    protected function dispatch(string $path, array $payload, string $requestId): array
    {
        try {
            $response = $this->http()->post($path, $payload);
        } catch (ConnectionException $e) {
            if ($this->isTimeout($e)) {
                throw new CaptureTimeoutException;
            }

            throw new BridgeUnavailableException;
        } catch (Throwable $e) {
            if ($this->isTimeout($e)) {
                throw new CaptureTimeoutException;
            }

            throw new BridgeUnavailableException;
        }

        if ($response->failed()) {
            throw $this->mapHttpFailure($response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new InvalidBridgeResponseException('Malformed biometric bridge response.');
        }

        return $body;
    }

    protected function mapVerificationResponse(array $payload, string $requestId): FingerprintVerificationResult
    {
        if (isset($payload['status']) && $payload['status'] === 'error') {
            return FingerprintVerificationResult::error($this->errorCategory($payload['message'] ?? ''), message: $payload['message'] ?? null, requestId: $requestId);
        }

        if (! isset($payload['result'])) {
            throw new InvalidBridgeResponseException('Biometric bridge did not return a verification result.');
        }

        return match ($payload['result']) {
            'match', 'matched' => FingerprintVerificationResult::match(
                confidence: $this->numericOrNull($payload['confidence'] ?? null),
                requestId: $requestId,
            ),
            'no_match', 'no-match', 'nomatch' => FingerprintVerificationResult::noMatch($requestId),
            default => throw new InvalidBridgeResponseException('Biometric bridge returned an unsupported verification result.'),
        };
    }

    protected function mapIdentificationResponse(array $payload, string $requestId): FingerprintIdentificationResult
    {
        if (isset($payload['status']) && $payload['status'] === 'error') {
            return FingerprintIdentificationResult::error($this->errorCategory($payload['message'] ?? ''), message: $payload['message'] ?? null, requestId: $requestId);
        }

        // Explicit no-match.
        if (isset($payload['matched']) && $payload['matched'] === false) {
            return FingerprintIdentificationResult::noMatch($requestId);
        }

        if (! isset($payload['candidate_id']) && ! isset($payload['matched_candidate_id'])) {
            throw new InvalidBridgeResponseException('Biometric bridge did not return an identification candidate.');
        }

        $candidateId = (string) ($payload['matched_candidate_id'] ?? $payload['candidate_id']);

        if ($candidateId === '') {
            throw new InvalidBridgeResponseException('Biometric bridge returned an empty candidate reference.');
        }

        return FingerprintIdentificationResult::match(
            $candidateId,
            confidence: $this->numericOrNull($payload['confidence'] ?? null),
            requestId: $requestId,
        );
    }

    protected function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function errorCategory(string $message): string
    {
        $msg = strtolower($message);

        if (str_contains($msg, 'timeout')) {
            return FingerprintVerificationResult::ERROR_TIMEOUT;
        }

        if (str_contains($msg, 'disconnect') || str_contains($msg, 'no device') || str_contains($msg, 'scanner not found')) {
            return FingerprintVerificationResult::ERROR_SCANNER_UNAVAILABLE;
        }

        if (str_contains($msg, 'quality') || str_contains($msg, 'poor')) {
            return FingerprintVerificationResult::ERROR_LOW_QUALITY;
        }

        if (str_contains($msg, 'ambiguous') || str_contains($msg, 'multiple')) {
            return FingerprintVerificationResult::ERROR_MALFORMED_RESPONSE;
        }

        return FingerprintVerificationResult::ERROR;
    }

    /**
     * Perform the HTTP capture/enroll request, validate the response, and map
     * it to the canonical FingerprintCaptureResult.
     */
    protected function performCapture(?string $fingerPosition = null): FingerprintCaptureResult
    {
        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId,
        ];

        if ($fingerPosition !== null) {
            $payload['finger_position'] = strtoupper((string) $fingerPosition);
        }

        try {
            $response = $this->http()->post($this->apiPrefix.'/fingerprints/capture', $payload);
        } catch (ConnectionException $e) {
            if ($this->isTimeout($e)) {
                throw new CaptureTimeoutException;
            }

            throw new BridgeUnavailableException;
        } catch (Throwable $e) {
            if ($this->isTimeout($e)) {
                throw new CaptureTimeoutException;
            }

            throw new BridgeUnavailableException;
        }

        if ($response->failed()) {
            throw $this->mapHttpFailure($response->status());
        }

        return $this->validateAndMapResponse($response->json() ?? []);
    }

    /**
     * Map a non-success HTTP status to a safe, distinct exception category.
     */
    protected function mapHttpFailure(int $status): BiometricBridgeException
    {
        return match (true) {
            $status === 401 || $status === 403 => new InvalidBridgeResponseException('Biometric bridge authentication failed.'),
            $status === 408 || $status === 504 => new CaptureTimeoutException,
            $status === 404 => new InvalidBridgeResponseException('Biometric bridge endpoint not found.'),
            default => new BridgeUnavailableException,
        };
    }

    protected function isTimeout(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains(strtolower($message), 'timed out')
            || str_contains(strtolower($message), 'operation timed out');
    }

    /**
     * Treat the bridge response as untrusted input: validate before mapping to
     * the canonical result to avoid persisting malformed/misconfigured data.
     */
    protected function validateAndMapResponse(array $payload): FingerprintCaptureResult
    {
        if (isset($payload['status']) && $payload['status'] === 'error') {
            throw $this->mapError($payload);
        }

        if (! isset($payload['template']) || ! is_string($payload['template'])) {
            throw new InvalidBridgeResponseException('Fingerprint template is missing.');
        }

        $template = $payload['template'];

        if ($template === '') {
            throw new InvalidBridgeResponseException('Fingerprint template is empty.');
        }

        $maxBytes = (int) ($this->bridgeConfig()['max_template_bytes'] ?? 65536);
        if (strlen($template) > $maxBytes) {
            throw new InvalidBridgeResponseException('Fingerprint template is too large.');
        }

        $qualityValue = $payload['quality_score'] ?? $payload['quality'] ?? null;

        if ($qualityValue !== null && ! is_numeric($qualityValue)) {
            throw new InvalidBridgeResponseException('Fingerprint quality is invalid.');
        }

        $quality = $qualityValue !== null ? (int) $qualityValue : null;
        if ($quality !== null && ($quality < 0 || $quality > 100)) {
            throw new InvalidBridgeResponseException('Fingerprint quality is invalid.');
        }

        $format = (string) ($payload['template_format'] ?? $payload['format'] ?? 'raw');
        if (! $this->isSupportedFormat($format)) {
            throw new InvalidBridgeResponseException('Unsupported fingerprint template format.');
        }

        $device = is_array($payload['device'] ?? null) ? $payload['device'] : [];

        return new FingerprintCaptureResult(
            template: $template,
            format: $format,
            quality: $quality,
            source: (string) ($payload['source'] ?? 'hardware'),
            deviceManufacturer: isset($device['vendor']) ? (string) $device['vendor'] : null,
            deviceModel: isset($device['model']) ? (string) $device['model'] : null,
            deviceSerial: isset($device['serial']) ? (string) $device['serial'] : null,
            sdkVersion: isset($payload['sdk_version']) ? (string) $payload['sdk_version'] : null,
        );
    }

    /**
     * Map a bridge-reported error to a distinct safe exception category.
     */
    protected function mapError(array $payload): BiometricBridgeException
    {
        $message = strtolower((string) ($payload['message'] ?? ''));

        if (str_contains($message, 'timeout')) {
            return new CaptureTimeoutException;
        }

        if (
            str_contains($message, 'disconnect')
            || str_contains($message, 'no device')
            || str_contains($message, 'scanner not found')
            || str_contains($message, 'busy')
            || str_contains($message, 'cancel')
        ) {
            return new ScannerOperationException;
        }

        if (str_contains($message, 'quality') || str_contains($message, 'poor')) {
            return new LowQualityCaptureException;
        }

        if (str_contains($message, 'unauthorized') || str_contains($message, 'forbidden') || str_contains($message, 'auth')) {
            return new InvalidBridgeResponseException('Biometric bridge authentication failed.');
        }

        return new InvalidBridgeResponseException('Fingerprint enrollment failed. Please try again.');
    }

    /**
     * Known template formats. We store the configured/supported format; we do
     * not claim a proprietary vendor produces a standard unless proven.
     */
    protected function isSupportedFormat(string $format): bool
    {
        return in_array($format, ['raw', 'iso_iec_19794_2', 'ansi_incits_378', 'wsq', 'mock_format'], true);
    }
}
