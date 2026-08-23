<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Widow extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'widows';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'nin',
        'reg_no',
        'skills',
        'address',
        'picture_url',
        'is_eligible',
        'is_married',
        'deceased_id',
        'child_sequence',
        'full_name',
        'married_at',
        'divorced_at',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'married_at' => 'datetime',
        'divorced_at' => 'datetime',
        'skills' => 'array',
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

        return $name !== '' ? $name : ($this->reg_no ?? 'Widow #'.$this->id);
    }

    /**
     * Mark widow as married and revoke eligibility.
     */
    public function markAsMarried(?string $notes = null, ?string $marriedAt = null): void
    {
        $this->update([
            'is_married' => true,
            'married_at' => $marriedAt ?? $this->married_at ?? now(),
            'is_eligible' => false,
        ]);

        // Revoke active ID cards
        $this->idCards()
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revocation_reason' => 'Beneficiary remarried',
            ]);

        // Log the event
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'notes' => $notes,
                'married_at' => $this->married_at,
                'event_type' => 'REMARRIED',
            ])
            ->log('REMARRIED');
    }

    /**
     * Reactivate widow relationship under original deceased after divorce.
     */
    public function reactivateAfterDivorce(?string $notes = null, ?string $divorcedAt = null): void
    {
        $this->update([
            'is_married' => false,
            'divorced_at' => $divorcedAt ?? now(),
            'is_eligible' => true,
        ]);

        // Log the event
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties([
                'notes' => $notes,
                'divorced_at' => $this->divorced_at,
                'event_type' => 'REACTIVATED_AFTER_DIVORCE',
            ])
            ->log('REACTIVATED_AFTER_DIVORCE');
    }

    public function idCards(): MorphMany
    {
        return $this->morphMany(IdCard::class, 'cardable');
    }

    public function prescriptions(): MorphMany
    {
        return $this->morphMany(Prescription::class, 'prescribable');
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    public function getCoordinatorNameAttribute(): ?string
    {
        return $this->deceased?->zone?->coordinator?->name;
    }

    public function zone(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Zone::class,
            Deceased::class,
            'id',          // Foreign key on Deceased (refers to Widow's deceased_id)
            'id',          // Foreign key on Zone
            'deceased_id', // Local key on Widow
            'zone_id'      // Local key on Deceased
        );
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function widowLoans(): HasMany
    {
        return $this->hasMany(WidowLoan::class);
    }

    public function canApplyForLoan(): bool
    {
        if ($this->is_married) {
            return false;
        }

        if (! $this->is_eligible) {
            return false;
        }

        // Block if there is any loan that is active (DRAFT, PENDING, APPROVED, DISBURSED, DEFAULTED)
        $hasActiveLoan = $this->widowLoans()
            ->whereIn('status', array_column(\App\Enums\WidowLoanStatus::activeStatuses(), 'value'))
            ->exists();
        if ($hasActiveLoan) {
            return false;
        }

        // Block if there is any written-off loan where reapplication is denied
        $hasDeniedWriteOff = $this->widowLoans()
            ->where('status', \App\Enums\WidowLoanStatus::WRITTEN_OFF->value)
            ->where('reapplication_allowed', false)
            ->exists();
        if ($hasDeniedWriteOff) {
            return false;
        }

        return true;
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

        static::creating(function ($model) {
            $model->full_name = trim(implode(' ', array_filter([
                $model->first_name,
                $model->middle_name,
                $model->last_name,
            ])));
        });

        static::created(function (Widow $widow) {
            $existingCount = static::withoutGlobalScopes()
                ->where('nin', $widow->nin)
                ->where('id', '!=', $widow->id)
                ->count();

            $eventType = $existingCount > 0 ? 'NEW_WIDOW_HOUSEHOLD_CREATED' : 'REGISTERED_AS_WIDOW';

            activity()
                ->performedOn($widow)
                ->causedBy(auth()->user())
                ->withProperties([
                    'deceased_id' => $widow->deceased_id,
                    'event_type' => $eventType,
                ])
                ->log($eventType);
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

        static::updated(function (Widow $widow) {
            if ($widow->wasChanged('picture_url')) {
                static::deleteStoredImage($widow->getOriginal('picture_url'));
            }
        });

        static::deleted(function (Widow $widow) {
            static::deleteStoredImage($widow->picture_url);
        });
    }

    protected static function deleteStoredImage(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
