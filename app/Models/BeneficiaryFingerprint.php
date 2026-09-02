<?php

namespace App\Models;

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
        'encrypted_template' => 'encrypted',
        'enrolled_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active' => 'boolean',
        'quality_score' => 'integer',
    ];

    protected $hidden = [
        'encrypted_template',
    ];

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
