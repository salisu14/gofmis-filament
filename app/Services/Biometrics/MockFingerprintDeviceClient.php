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

    public function verify(string $template): bool
    {
        return true;
    }

    public function identify(array $templates): ?string
    {
        if (empty($templates)) {
            return null;
        }

        return array_key_first($templates);
    }
}
