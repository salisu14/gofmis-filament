<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Orphan extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'nin',
        'reg_no',
        'birth_date',
        'address',
        'picture_url',
        'deceased_id',
        'islamiyya_education_id',
        'western_education_id',
        'birth_certificate_path',
        'status',
        'rejection_reason',
        'is_eligible',
        'age',
        'full_name',
        'married_at',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'birth_date' => 'date',
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'married_at' => 'datetime',
    ];

    public function prescriptions(): MorphMany
    {
        return $this->morphMany(Prescription::class, 'prescribable');
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class)->nullable();
    }

    public function westernEducation(): BelongsTo
    {
        return $this->belongsTo(WesternEducation::class)->nullable();
    }

    public function islamiyyaEducation(): BelongsTo
    {
        return $this->belongsTo(IslamiyyaEducation::class)->nullable();
    }

    public function vocationalSkills(): BelongsToMany
    {
        return $this->belongsToMany(VocationalSkill::class, 'orphan_vocational_skill')
            ->withPivot('specify')
            ->withTimestamps();
    }

    /**
     * Get all interventions for the orphan (Education, Medical, Welfare, etc).
     */
    public function interventionRequests(): HasMany
    {
        return $this->hasMany(InterventionRequest::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->full_name = trim(implode(' ', array_filter([
                $model->first_name,
                $model->middle_name,
                $model->last_name
            ])));
        });

        static::updating(function ($model) {
            if ($model->isDirty(['first_name', 'middle_name', 'last_name'])) {
                $model->full_name = trim(implode(' ', array_filter([
                    $model->first_name,
                    $model->middle_name,
                    $model->last_name
                ])));
            }
        });
    }
}
