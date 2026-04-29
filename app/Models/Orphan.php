<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Scopes\EligibleOrphanScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orphan extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'orphans';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'full_name',
        'gender',
        'nin',
        'reg_no',
        'birth_date',
        'address',
        'picture_url',
        'deceased_id',
        'child_sequence',
        'has_birth_cert',
        'birth_certificate_path',
        'status',
        'rejection_reason',
        'is_eligible',
        'age',
        'is_married',
        'married_at',
    ];

    protected $casts = [
        'gender' => Gender::class,
        'birth_date' => 'date',
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'has_birth_cert' => 'boolean',
        'married_at' => 'datetime',
    ];

    public function idCards(): MorphMany
    {
        return $this->morphMany(IdCard::class, 'cardable');
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    public function prescriptions(): MorphMany
    {
        return $this->morphMany(Prescription::class, 'prescribable');
    }

    public function interventionRequests(): HasMany
    {
        return $this->hasMany(InterventionRequest::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function zone(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Zone::class,
            Deceased::class,
            'id',           // Foreign key on Deceased
            'id',           // Foreign key on Zone
            'deceased_id',  // Local key on Orphan
            'zone_id'       // Local key on Deceased
        );
    }

    public function educations(): HasMany
    {
        return $this->hasMany(OrphanEducation::class);
    }

    public function vocationalSkills(): BelongsToMany
    {
        return $this->belongsToMany(
            VocationalSkill::class,
            'orphan_vocational_skills',
            'orphan_id',
            'vocational_skill_id'
        )->withPivot(['specify'])->withTimestamps();
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }

    public function scopeEligible($query)
    {
        return $query->where('is_eligible', true);
    }

    public static function getNonEligibleOrphans()
    {
        return Orphan::withoutGlobalScope(EligibleOrphanScope::class)
            ->where('is_eligible', false)
            ->get();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new EligibleOrphanScope);

        // ✅ FIXED: Use whereHas to filter through deceased relationship
        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (!$user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            $query->whereHas('deceased', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        });

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
