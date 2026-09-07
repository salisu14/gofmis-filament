<?php

namespace App\Services\Biometrics;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Dedicated encryption boundary for biometric fingerprint templates.
 *
 * Fingerprint templates are highly sensitive identity data and must not
 * depend on the application's general-purpose APP_KEY for their encryption
 * boundary. New templates are wrapped in a versioned envelope and encrypted
 * with a dedicated biometric key, isolated from APP_KEY.
 *
 * Envelope format:
 *   biometric:v1:<base64-encrypted-payload>
 *
 * The version prefix allows the application to distinguish:
 *   - "biometric:v1:" -> encrypted with the dedicated biometric key (current)
 *   - anything else   -> legacy payload produced by Laravel's "encrypted" cast
 *                        using APP_KEY (must be read for migration but is never
 *                        written for new data).
 *
 * The service fails closed: if a dedicated biometric key is missing or invalid,
 * new writes raise a RuntimeException instead of silently falling back to APP_KEY.
 */
class BiometricTemplateCipher
{
    /**
     * The versioned envelope prefix for current biometric encryption.
     */
    public const FORMAT_PREFIX = 'biometric:';

    protected ?string $legacyKeyVersion = null;

    protected ?Encrypter $encrypter = null;

    /**
     * Whether the configured dedicated biometric key is usable for NEW writes.
     */
    public function isKeyAvailable(): bool
    {
        return $this->encrypter() !== null;
    }

    /**
     * Encrypt a plaintext template into a versioned envelope using the
     * dedicated biometric key.
     *
     * @throws RuntimeException when the dedicated key is missing or invalid.
     */
    public function encrypt(#[\SensitiveParameter] string $plaintext, ?int $keyVersion = null): string
    {
        $encrypter = $this->encrypter();

        if ($encrypter === null) {
            throw new RuntimeException(
                'BIOMETRICS_ENCRYPTION_KEY is not configured or is invalid. '
                .'Biometric template encryption cannot proceed without a dedicated key.'
            );
        }

        $version = $keyVersion ?? config('biometrics.encryption.key_version', 1);

        $payload = $encrypter->encrypt($plaintext, false);

        return static::FORMAT_PREFIX.'v'.$version.':'.$payload;
    }

    /**
     * Decrypt a stored (possibly legacy) template envelope back to plaintext.
     *
     * Handles both the current dedicated-key envelope and legacy APP_KEY
     * ciphertext so existing rows remain readable during migration.
     *
     * @throws DecryptException|RuntimeException on invalid or non-decryptable data.
     */
    public function decrypt(#[\SensitiveParameter] string $ciphertext): string
    {
        if (str_starts_with($ciphertext, static::FORMAT_PREFIX.'v')) {
            // Current envelope -> strip the "biometric:v<version>:" prefix and
            // decrypt with the dedicated key. The payload never contains ":", so
            // the last colon in the envelope is the version separator.
            $encrypter = $this->encrypter();

            if ($encrypter === null) {
                throw new RuntimeException(
                    'BIOMETRICS_ENCRYPTION_KEY is not configured or is invalid; '
                    .'cannot decrypt the dedicated-key template envelope.'
                );
            }

            $payload = substr($ciphertext, strrpos($ciphertext, ':') + 1);

            return $encrypter->decrypt($payload, false);
        }

        // Legacy pipeline: Laravel "encrypted" cast ciphertext encrypted with APP_KEY.
        return Crypt::decryptString($ciphertext);
    }

    /**
     * Whether a stored value is the current dedicated-key envelope.
     */
    public function isCurrentEnvelope(string $ciphertext): bool
    {
        return str_starts_with($ciphertext, static::FORMAT_PREFIX);
    }

    /**
     * Re-encrypt legacy APP_KEY ciphertext into the current dedicated-key
     * envelope. Returns null if the value is already current.
     *
     * @throws DecryptException|RuntimeException when the legacy value cannot be
     *                                           read or the dedicated key is unusable.
     */
    public function reencryptLegacy(#[\SensitiveParameter] string $legacyCiphertext): ?string
    {
        if ($this->isCurrentEnvelope($legacyCiphertext)) {
            return null;
        }

        $plaintext = $this->decrypt($legacyCiphertext);

        return $this->encrypt($plaintext);
    }

    /**
     * Build the dedicated-key encrypter, or null when configuration is missing/invalid.
     */
    protected function encrypter(): ?Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        $base64Key = (string) config('biometrics.encryption.key', '');

        if ($base64Key === '') {
            return null;
        }

        $decoded = base64_decode($base64Key, true);
        if ($decoded === false) {
            return null;
        }

        // The cipher requires a 32-byte key.
        if (strlen($decoded) !== 32) {
            return null;
        }

        $this->encrypter = new Encrypter($decoded, config('biometrics.encryption.cipher', 'aes-256-cbc'));

        return $this->encrypter;
    }
}
