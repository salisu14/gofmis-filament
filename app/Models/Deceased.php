<?php

namespace App\Models;

use App\Enums\VulnerabilityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deceased extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'deceased';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'nin',
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
    ];

    protected $casts = [
        'has_death_cert' => 'boolean',
        'age' => 'integer',
        'date_registered' => 'date',
        'number_of_orphans_left' => 'integer',
        'number_of_widows_left' => 'integer',
        'vulnerability_status' => VulnerabilityStatus::class,
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function coordinator(): \Illuminate\Database\Eloquent\Relations\HasOneThrough|Deceased
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
        return $this->hasMany(Orphan::class);
    }

    public function widows(): HasMany
    {
        return $this->hasMany(Widow::class);
    }

    public function welfares(): BelongsToMany
    {
        return $this->belongsToMany(Welfare::class, 'welfare_deceased')
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

            if (!$user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            // ✅ FIXED: Get zone_id from coordinatedZone relationship
            $zoneId = $user->coordinatedZone?->id;

            if (!$zoneId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('zone_id', $zoneId);
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
