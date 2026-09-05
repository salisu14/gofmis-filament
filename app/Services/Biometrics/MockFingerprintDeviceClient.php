<?php

namespace App\Services\Biometrics;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
use Illuminate\Support\Str;

class MockFingerprintDeviceClient implements FingerprintDeviceClientInterface
{
    public function health(): array
    {
        return [
            'status' => 'ok',
            'message' => 'Mock Biometric Bridge is running (Hardware Unavailable)',
        ];
    }

    public function capture(): array
    {
        return [
            'status' => 'ok',
            'template' => 'MOCK_TEMPLATE_DO_NOT_USE_IN_PRODUCTION_'.Str::random(32),
            'quality' => config('biometrics.mock.force_low_quality', false) ? 35 : 85,
            'status' => config('biometrics.mock.force_low_quality', false) ? 'low_quality' : 'ok',
            'source' => 'mock',
            'message' => 'Capture successful',
        ];
    }

    public function enroll(): array
    {
        return [
            'status' => 'ok',
            'template' => 'MOCK_TEMPLATE_DO_NOT_USE_IN_PRODUCTION_'.Str::random(64),
            'quality' => config('biometrics.mock.force_low_quality', false) ? 40 : 95,
            'status' => config('biometrics.mock.force_low_quality', false) ? 'low_quality' : 'ok',
            'format' => 'mock_format',
            'device_manufacturer' => 'SecuGen (Mock)',
            'device_model' => 'Hamster Plus HSDU03P (Mock)',
            'device_serial' => 'MOCK-'.Str::upper(Str::random(8)),
            'sdk_version' => 'Mock-SDK-1.0',
            'source' => 'mock',
            'message' => 'Enrollment successful',
        ];
    }

    public function verify(string $template, ?string $templateFormat = null): FingerprintVerificationResult
    {
        // Deterministic, controllable test mode: force a specific outcome via
        // config so automated tests are stable and never depend on randomness.
        $forced = config('biometrics.mock.verify_outcome');

        return match ($forced) {
            'no_match' => FingerprintVerificationResult::noMatch(),
            'scanner_unavailable' => FingerprintVerificationResult::error(FingerprintVerificationResult::ERROR_SCANNER_UNAVAILABLE),
            'timeout' => FingerprintVerificationResult::error(FingerprintVerificationResult::ERROR_TIMEOUT),
            'low_quality' => FingerprintVerificationResult::error(FingerprintVerificationResult::ERROR_LOW_QUALITY),
            'malformed' => FingerprintVerificationResult::error(FingerprintVerificationResult::ERROR_MALFORMED_RESPONSE),
            default => FingerprintVerificationResult::match(confidence: 0.99),
        };
    }

    public function identify(array $candidates): FingerprintIdentificationResult
    {
        $forced = config('biometrics.mock.identify_outcome');

        if ($forced === 'no_match') {
            return FingerprintIdentificationResult::noMatch();
        }

        if ($forced === 'scanner_unavailable') {
            return FingerprintIdentificationResult::error(FingerprintIdentificationResult::ERROR_SCANNER_UNAVAILABLE);
        }

        if ($forced === 'timeout') {
            return FingerprintIdentificationResult::error(FingerprintIdentificationResult::ERROR_TIMEOUT);
        }

        if ($forced === 'malformed') {
            return FingerprintIdentificationResult::error(FingerprintIdentificationResult::ERROR_MALFORMED_RESPONSE);
        }

        if ($forced === 'ambiguous') {
            return FingerprintIdentificationResult::error(FingerprintIdentificationResult::ERROR_AMBIGUOUS);
        }

        if (empty($candidates)) {
            return FingerprintIdentificationResult::noMatch();
        }

        $first = $candidates[0] ?? [];

        return FingerprintIdentificationResult::match($first['candidate_id'] ?? array_key_first($candidates), confidence: 0.98);
    }
}
