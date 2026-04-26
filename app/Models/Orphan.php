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
        'married_at' => 'datetime',
    ];

    /* -----------------------------
     | Core Relationships
     ------------------------------*/

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

    public function zone(): \Illuminate\Database\Eloquent\Relations\HasOneThrough|Orphan
    {
        return $this->hasOneThrough(
            Zone::class,
            Deceased::class,
            'id',        // Foreign key on Deceased table (local key on Orphan relation)
            'id',        // Foreign key on Zone table
            'deceased_id', // Foreign key on Orphan table
            'zone_id'     // Foreign key on Deceased table
        );
    }

    /* -----------------------------
     | EDUCATION (NEW UNIFIED MODEL)
     ------------------------------*/

    public function educations(): HasMany
    {
        return $this->hasMany(OrphanEducation::class);
    }

    /* -----------------------------
     | VOCATIONAL SKILLS (KEEP THIS INDEPENDENT)
     ------------------------------*/

    public function vocationalSkills(): BelongsToMany
    {
        return $this->belongsToMany(
            VocationalSkill::class,
            'orphan_vocational_skills',
            'orphan_id',
            'vocational_skill_id'
        )->withPivot(['specify'])
            ->withTimestamps();
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

    public static function getNonEligibleOrphans(): Orphan
    {
        return Orphan::withoutGlobalScope(EligibleOrphanScope::class)
            ->where('is_eligible', false)
            ->get();
    }

    /* -----------------------------
     | AUTO NAME GENERATION
     ------------------------------*/

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new EligibleOrphanScope);

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
