<?php

namespace App\Services\Biometrics;

/**
 * Canonical, validated biometric capture/enroll result returned by a
 * FingerprintDeviceClient implementation.
 *
 * The shape deliberately matches the fields already consumed by
 * FingerprintsRelationManager and persisted on BeneficiaryFingerprint, so
 * changing the device client never requires changing domain/UI code.
 */
class FingerprintCaptureResult
{
    public function __construct(
        public string $template,
        public string $format = 'raw',
        public ?int $quality = null,
        public string $source = 'hardware',
        public ?string $deviceManufacturer = null,
        public ?string $deviceModel = null,
        public ?string $deviceSerial = null,
        public ?string $sdkVersion = null,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => 'ok',
            'template' => $this->template,
            'format' => $this->format,
            'quality' => $this->quality,
            'source' => $this->source,
            'device_manufacturer' => $this->deviceManufacturer,
            'device_model' => $this->deviceModel,
            'device_serial' => $this->deviceSerial,
            'sdk_version' => $this->sdkVersion,
            'message' => 'Capture successful',
        ];
    }
}
