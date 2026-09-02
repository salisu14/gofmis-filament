<?php

namespace App\Services\Biometrics;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;

class HttpBiometricBridgeClient implements FingerprintDeviceClientInterface
{
    public function health(): array
    {
        return [
            'status' => 'error',
            'message' => 'Fingerprint scanner unavailable',
        ];
    }

    public function capture(): array
    {
        return [
            'status' => 'error',
            'message' => 'Fingerprint scanner unavailable',
        ];
    }

    public function enroll(): array
    {
        return [
            'status' => 'error',
            'message' => 'Fingerprint scanner unavailable',
        ];
    }

    public function verify(string $template): bool
    {
        return false;
    }

    public function identify(array $templates): ?string
    {
        return null;
    }
}
