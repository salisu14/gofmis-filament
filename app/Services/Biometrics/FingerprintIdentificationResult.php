<?php

namespace App\Services\Biometrics;

/**
 * Canonical result of a 1:N fingerprint identification search.
 *
 * A candidate fingerprint id is returned only on an explicit match. When the
 * bridge cannot fulfil the request (scanner unavailable, timeout, malformed
 * response) we surface an ERROR status rather than a misleading "no match".
 */
class FingerprintIdentificationResult
{
    public const MATCH = 'match';

    public const NO_MATCH = 'no_match';

    public const ERROR = 'error';

    public const ERROR_SCANNER_UNAVAILABLE = 'scanner_unavailable';

    public const ERROR_TIMEOUT = 'timeout';

    public const ERROR_LOW_QUALITY = 'low_quality';

    public const ERROR_MALFORMED_RESPONSE = 'malformed_response';

    public const ERROR_AMBIGUOUS = 'ambiguous';

    public function __construct(
        public string $status,
        public ?string $candidateId = null,
        public ?float $confidence = null,
        public ?string $message = null,
        public ?string $requestId = null,
    ) {}

    public function isMatch(): bool
    {
        return $this->status === self::MATCH && $this->candidateId !== null;
    }

    public function isNoMatch(): bool
    {
        return $this->status === self::NO_MATCH;
    }

    public function isError(): bool
    {
        return ! in_array($this->status, [self::MATCH, self::NO_MATCH], true);
    }

    public static function match(string $candidateId, ?float $confidence = null, ?string $requestId = null): self
    {
        return new self(self::MATCH, $candidateId, $confidence, 'Match confirmed', $requestId);
    }

    public static function noMatch(?string $requestId = null): self
    {
        return new self(self::NO_MATCH, null, null, 'No matching beneficiary found.', $requestId);
    }

    public static function error(string $category = self::ERROR, ?string $message = null, ?string $requestId = null): self
    {
        return new self($category, null, null, $message ?? 'Identification could not be completed.', $requestId);
    }
}
