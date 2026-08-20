<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Models\Scopes\EligibleOrphanScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
        'status' => OrphanStatus::class,
        'birth_date' => 'date',
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'has_birth_cert' => 'boolean',
        'married_at' => 'datetime',
    ];

    public function setPictureUrlAttribute($value): void
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        $this->attributes['picture_url'] = $value;
    }

    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->full_name)) {
            return $this->full_name;
        }

        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));

        return $name !== '' ? $name : ($this->reg_no ?? 'Orphan #'.$this->id);
    }

    /**
     * Mark orphan (girl) as married and revoke eligibility.
     */
    public function markAsMarried(?string $notes = null, mixed $marriedAt = null): void
    {
        $this->update([
            'is_married' => true,
            'married_at' => $marriedAt ?? now(),
            'is_eligible' => false,
            'status' => OrphanStatus::ARCHIVED,
            'rejection_reason' => $notes ?? 'Archived: female orphan is married.',
        ]);

        $this->revokeActiveBenefits('Beneficiary got married.');

        // Log the event
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['notes' => $notes])
            ->log('orphan_marked_married');
    }

    public function archiveForIneligibility(string $reason): void
    {
        $this->update([
            'is_eligible' => false,
            'status' => OrphanStatus::ARCHIVED,
            'rejection_reason' => $this->archiveReasonText($reason),
        ]);

        $this->revokeActiveBenefits($this->archiveReasonText($reason));
    }

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

    public function getCoordinatorNameAttribute(): ?string
    {
        return $this->deceased?->zone?->coordinator?->name;
    }

    public function educations(): HasMany
    {
        return $this->hasMany(OrphanEducation::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function activeSponsorships(): HasMany
    {
        return $this->sponsorships()
            ->whereDate('start_date', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', now());
            });
    }

    public function hasActiveSponsorship(): bool
    {
        return $this->activeSponsorships()->exists();
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
        return (float) $this->educations()
            ->get()
            ->sum(fn (OrphanEducation $education): float => $education->total_paid);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->educations()
            ->get()
            ->sum(fn (OrphanEducation $education): float => $education->balance);
    }

    public function scopeEligible($query)
    {
        (new EligibleOrphanScope)->apply($query, $query->getModel());

        return $query;
    }

    public function hasHistoricalRecords(): bool
    {
        return $this->educations()->exists()
            || $this->prescriptions()->exists()
            || $this->interventionRequests()->exists()
            || $this->interventions()->exists()
            || $this->sponsorships()->exists()
            || $this->idCards()->exists();
    }

    public function isEligibleForIntervention(): bool
    {
        if ($this->is_married) {
            return false;
        }

        return $this->is_eligible;
    }

    public static function getNonEligibleOrphans()
    {
        return Orphan::where('is_eligible', false)
            ->where('is_married', true)
            ->get();
    }

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            // ✅ FIXED: Use coordinatedZone instead of zone_id
            $zoneId = $user->coordinatedZone?->id;

            if (! $zoneId) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('deceased', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        });

        $preventDelete = function (Orphan $orphan) {
            $status = $orphan->status instanceof OrphanStatus ? $orphan->status : OrphanStatus::tryFrom((string) $orphan->status);

            if ($status === OrphanStatus::ARCHIVED || ! $orphan->is_eligible) {
                throw new \DomainException('Archived orphan records cannot be deleted as they preserve historical beneficiary records.');
            }

            if ($orphan->hasHistoricalRecords()) {
                throw new \DomainException('Beneficiaries with historical records cannot be deleted.');
            }
        };

        static::deleting($preventDelete);

        static::saving(function ($model) {
            if ($model->birth_date) {
                $model->age = \Carbon\Carbon::parse($model->birth_date)->age;
            }

            $gender = $model->gender instanceof Gender ? $model->gender : Gender::tryFrom((string) $model->gender);

            // Prevent silent reactivation of archived/ineligible records
            if ($model->exists) {
                $originalStatus = $model->getOriginal('status');
                $originalStatusValue = $originalStatus instanceof OrphanStatus ? $originalStatus : OrphanStatus::tryFrom((string) $originalStatus);
                $originalEligible = (bool) $model->getOriginal('is_eligible');

                if ($originalStatusValue === OrphanStatus::ARCHIVED || ! $originalEligible) {
                    $model->status = OrphanStatus::ARCHIVED;
                    $model->is_eligible = false;
                }
            }

            if (
                ($gender === Gender::MALE && $model->age >= 18) ||
                ($gender === Gender::FEMALE && $model->is_married)
            ) {
                $model->is_eligible = false;
                $model->status = OrphanStatus::ARCHIVED;

                if (! $model->rejection_reason) {
                    $model->rejection_reason = $gender === Gender::MALE
                        ? 'Archived: male orphan is 18 years or older.'
                        : 'Archived: female orphan is married.';
                }
            }
        });

        static::creating(function (Orphan $model) {
            $model->full_name = trim(implode(' ', array_filter([
                $model->first_name,
                $model->middle_name,
                $model->last_name,
            ])));

            $model->status ??= OrphanStatus::PENDING_REVIEW;
            $model->is_eligible ??= false;
        });

        static::updating(function ($model) {
            if ($model->isDirty(['first_name', 'middle_name', 'last_name'])) {
                $model->full_name = trim(implode(' ', array_filter([
                    $model->first_name,
                    $model->middle_name,
                    $model->last_name,
                ])));
            }
        });

        static::updated(function (Orphan $orphan) {
            if ($orphan->wasChanged('picture_url')) {
                static::deleteStoredImage($orphan->getOriginal('picture_url'));
            }

            if (
                $orphan->status === OrphanStatus::ARCHIVED &&
                $orphan->wasChanged(['status', 'is_eligible', 'is_married'])
            ) {
                $orphan->revokeActiveBenefits($orphan->rejection_reason ?? 'Orphan is no longer eligible for benefits.');
            }
        });

        static::deleted(function (Orphan $orphan) {
            static::deleteStoredImage($orphan->picture_url);
        });
    }

    protected static function deleteStoredImage(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    protected function revokeActiveBenefits(string $reason): void
    {
        $this->idCards()
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        $this->interventionRequests()
            ->whereIn('status', ['pending', 'draft'])
            ->update([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ]);
    }

    protected function archiveReasonText(string $reason): string
    {
        return match ($reason) {
            'AGE_LIMIT' => 'Archived: male orphan is 18 years or older.',
            'MARRIAGE' => 'Archived: female orphan is married.',
            default => $reason,
        };
    }
}
