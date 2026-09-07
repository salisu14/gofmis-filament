<?php

namespace App\Services\Biometrics;

/**
 * Canonical result of a 1:1 fingerprint verification.
 *
 * The outcome is an explicit, non-castable status so consumer UI can show
 * MATCH, NO_MATCH and scanner ERROR distinctly. We never collapse a scanner
 * error or low-quality capture into "no match".
 */
class FingerprintVerificationResult
{
    public const MATCH = 'match';

    public const NO_MATCH = 'no_match';

    public const ERROR = 'error';

    public const ERROR_SCANNER_UNAVAILABLE = 'scanner_unavailable';

    public const ERROR_TIMEOUT = 'timeout';

    public const ERROR_LOW_QUALITY = 'low_quality';

    public const ERROR_MALFORMED_RESPONSE = 'malformed_response';

    public function __construct(
        public string $status,
        public ?float $confidence = null,
        public ?string $message = null,
        public ?string $requestId = null,
    ) {}

    public function isMatch(): bool
    {
        return $this->status === self::MATCH;
    }

    public function isNoMatch(): bool
    {
        return $this->status === self::NO_MATCH;
    }

    public function isError(): bool
    {
        return ! in_array($this->status, [self::MATCH, self::NO_MATCH], true);
    }

    public static function match(?float $confidence = null, ?string $requestId = null): self
    {
        return new self(self::MATCH, $confidence, 'Match confirmed', $requestId);
    }

    public static function noMatch(?string $requestId = null): self
    {
        return new self(self::NO_MATCH, null, 'No match', $requestId);
    }

    public static function error(
        string $category = self::ERROR,
        ?string $message = null,
        ?string $requestId = null,
    ): self {
        return new self($category, null, $message ?? 'Verification could not be completed.', $requestId);
    }
}
