<?php

namespace App\Models;

use App\Enums\VulnerabilityStatus;
use App\Models\Scopes\EligibleOrphanScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deceased extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'deceased';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'nin',
        'has_nin',
        'reg_no',
        'age',
        'address',
        'vulnerability_status',
        'date_registered',
        'death_cause',
        'death_place',
        'occupation',
        'has_death_cert',
        'death_cert_url',
        'number_of_orphans_left',
        'number_of_widows_left',
        'guardian_name',
        'guardian_phone',
        'zone_id', // ✅ IMPORTANT
        'full_name',
        'date_of_birth',
        'date_of_death',
    ];

    protected $casts = [
        'has_nin' => 'boolean',
        'has_death_cert' => 'boolean',
        'age' => 'integer',
        'date_registered' => 'date',
        'date_of_birth' => 'date',
        'date_of_death' => 'date',
        'number_of_orphans_left' => 'integer',
        'number_of_widows_left' => 'integer',
        'vulnerability_status' => VulnerabilityStatus::class,
    ];

    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->full_name)) {
            return $this->full_name;
        }

        $name = trim(collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->implode(' '));

        if ($name !== '') {
            return $name;
        }

        return ! empty($this->reg_no) ? "Deceased ({$this->reg_no})" : 'Unnamed deceased record';
    }

    public function getAgeAtDeathAttribute(): ?int
    {
        if ($this->date_of_birth && $this->date_of_death) {
            return $this->date_of_birth->diffInYears($this->date_of_death);
        }

        // Fallback to legacy age if dates are missing
        return $this->age ?: null;
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function coordinator(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Zone::class,
            'id',             // Foreign key on Zone
            'id',             // Foreign key on User
            'zone_id',        // Local key on Deceased
            'coordinator_id'  // Local key on Zone
        );
    }

    public function getCoordinatorNameAttribute(): ?string
    {
        return $this->zone?->coordinator?->name;
    }

    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class)
            ->withoutGlobalScope(EligibleOrphanScope::class);
    }

    public function eligibleOrphans(): HasMany
    {
        return $this->hasMany(Orphan::class)->eligible();
    }

    public function widows(): HasMany
    {
        return $this->hasMany(Widow::class);
    }

    // Legacy welfare relationship removed as part of canonical consolidation.

    public function welfarePackages(): BelongsToMany
    {
        // Links Deceased to WelfarePackage through the welfare_beneficiaries pivot table
        return $this->belongsToMany(WelfarePackage::class, 'welfare_beneficiaries', 'deceased_id', 'welfare_package_id')
            ->withPivot('collection_status')
            ->withTimestamps();
    }

    /**
     * Get all zone transfers for this deceased.
     */
    public function zoneTransfers(): HasMany
    {
        return $this->hasMany(ZoneTransfer::class);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['admin', 'super_admin']) || $user->isDemoObserver()) {
                return;
            }

            // ✅ FIXED: Get zone_id from coordinatedZone relationship
            $zoneId = $user->coordinatedZone?->id;

            if (! $zoneId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('zone_id', $zoneId);
        });

        static::saving(function ($model) {
            if ($model->has_nin === null) {
                $model->has_nin = filled($model->nin);
            }

            if (! $model->has_nin) {
                $model->nin = null;
            }

            $today = \Carbon\Carbon::today();

            if ($model->date_of_birth && \Carbon\Carbon::parse($model->date_of_birth)->greaterThan($today)) {
                throw new \InvalidArgumentException('Date of Birth cannot be in the future.');
            }

            if ($model->date_of_death && \Carbon\Carbon::parse($model->date_of_death)->greaterThan($today)) {
                throw new \InvalidArgumentException('Date of Death cannot be in the future.');
            }

            if ($model->date_registered && \Carbon\Carbon::parse($model->date_registered)->greaterThan($today)) {
                throw new \InvalidArgumentException('Date Registered cannot be in the future.');
            }

            if ($model->date_of_birth && $model->date_of_death && \Carbon\Carbon::parse($model->date_of_death)->lessThan(\Carbon\Carbon::parse($model->date_of_birth))) {
                throw new \InvalidArgumentException('Date of Death cannot be earlier than Date of Birth.');
            }

            if ($model->date_registered && $model->date_of_death && \Carbon\Carbon::parse($model->date_registered)->lessThan(\Carbon\Carbon::parse($model->date_of_death))) {
                throw new \InvalidArgumentException('Date Registered cannot be earlier than Date of Death.');
            }
        });

        static::creating(function ($model) {
            $model->full_name = trim(implode(' ', array_filter([
                $model->first_name,
                $model->middle_name,
                $model->last_name,
            ])));
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
    }
}
