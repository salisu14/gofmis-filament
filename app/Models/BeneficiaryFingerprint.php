<?php

namespace App\Models;

use App\Services\Biometrics\BiometricTemplateCipher;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class BeneficiaryFingerprint extends Model
{
    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->is_active) {
                $exists = static::where('beneficiary_type', $model->beneficiary_type)
                    ->where('beneficiary_id', $model->beneficiary_id)
                    ->where('finger_position', $model->finger_position)
                    ->where('is_active', true)
                    ->where('id', '!=', $model->id)
                    ->exists();
                if ($exists) {
                    throw new \Exception('Duplicate active finger position for beneficiary.');
                }
            }
        });
    }

    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'beneficiary_type',
        'beneficiary_id',
        'finger_position',
        'template_format',
        'encrypted_template',
        'template_version',
        'key_version',
        'quality_score',
        'source',
        'device_manufacturer',
        'device_model',
        'device_serial',
        'sdk_version',
        'enrolled_by',
        'enrolled_at',
        'last_verified_at',
        'is_active',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active' => 'boolean',
        'quality_score' => 'integer',
    ];

    protected $hidden = [
        'encrypted_template',
        'decrypted_template',
    ];

    /**
     * The dedicated biometric cipher instance (resolved lazily).
     */
    protected ?BiometricTemplateCipher $cipher = null;

    /**
     * Encrypt plaintext templates with the dedicated biometric key when they
     * are assigned to the model. Already-versioned envelopes pass through
     * unchanged so re-encryption and seeding never double-encrypt.
     */
    public function setEncryptedTemplateAttribute(#[\SensitiveParameter] string $value): void
    {
        if ($this->cipherInstance()->isCurrentEnvelope($value)) {
            // Already an envelope (re-encryption / seeding): store as-is and
            // record the key version embedded in the envelope when possible.
            $this->attributes['encrypted_template'] = $value;
            $version = $this->envelopeKeyVersion($value);
            if ($version !== null) {
                $this->attributes['key_version'] = $version;
            }

            return;
        }

        $this->attributes['encrypted_template'] = $this->cipherInstance()->encrypt($value);
        $this->attributes['key_version'] = (int) config('biometrics.encryption.key_version', 1);
    }

    /**
     * Read the stored template. Returns the plaintext template for in-app
     * domain/device callers while remaining hidden from serialization.
     */
    public function getEncryptedTemplateAttribute(): ?string
    {
        $ciphertext = $this->attributes['encrypted_template'] ?? null;

        if ($ciphertext === null) {
            return null;
        }

        return $this->cipherInstance()->decrypt($ciphertext);
    }

    /**
     * Explicit accessor for internal verification/enrollment logic that needs
     * the plaintext template. Same as reading the attribute but named so intent
     * is clear. Returns null when nothing is stored.
     */
    public function decryptedTemplate(): ?string
    {
        return $this->encrypted_template;
    }

    /**
     * True when the stored value uses the current dedicated-key envelope.
     */
    public function usesCurrentEnvelope(): bool
    {
        $ciphertext = $this->getRawOriginal('encrypted_template');

        return $this->cipherInstance()->isCurrentEnvelope((string) $ciphertext);
    }

    /**
     * Re-encrypt this record's template into the current envelope, if it is a
     * legacy APP_KEY payload. Returns true when the record was rewritten.
     */
    public function reencryptTemplate(): bool
    {
        $ciphertext = $this->getRawOriginal('encrypted_template');

        if ($ciphertext === null || $this->cipherInstance()->isCurrentEnvelope($ciphertext)) {
            return false;
        }

        try {
            $updated = $this->cipherInstance()->reencryptLegacy($ciphertext);
        } catch (\Throwable $e) {
            // Failure-safe: never destroy existing ciphertext we could not read.
            return false;
        }

        if ($updated === null) {
            return false;
        }

        $this->attributes['encrypted_template'] = $updated;
        $this->attributes['key_version'] = (int) config('biometrics.encryption.key_version', 1);

        // Persist without re-triggering the mutator (value is already an envelope).
        $this->saveQuietly();

        return true;
    }

    /**
     * Extract the key version embedded in a "biometric:v<version>:" envelope,
     * or null when the value is not the current envelope format.
     */
    protected function envelopeKeyVersion(string $value): ?int
    {
        if (! preg_match('/^biometric:v(\d+):/', $value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    protected function cipherInstance(): BiometricTemplateCipher
    {
        if ($this->cipher === null) {
            $this->cipher = app(BiometricTemplateCipher::class);
        }

        return $this->cipher;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->dontLogIfAttributesChangedOnly(['last_verified_at'])
            ->logExcept(['encrypted_template']);
    }

    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }

    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
