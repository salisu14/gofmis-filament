<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'is_married' => 'boolean',
        'married_at' => 'datetime',
        'skills' => 'array',
    ];

    /**
     * Mark widow as married and revoke eligibility.
     */
    public function markAsMarried(?string $notes = null): void
    {
        $this->update([
            'is_married' => true,
            'married_at' => now(),
            'is_eligible' => false,
        ]);

        // Deactivate ID cards
        $this->idCards()->where('status', 'active')->update(['status' => 'inactive']);

        // Cancel pending intervention requests
        $this->interventionRequests()
            ->whereIn('status', ['pending', 'draft'])
            ->update(['status' => 'cancelled', 'notes' => 'Beneficiary got married']);

        // Log the event
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['notes' => $notes])
            ->log('widow_marked_married');
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
        $activeLoan = $this->widowLoans()->whereNotIn('status', [
            \App\Enums\WidowLoanStatus::COMPLETED->value,
            \App\Enums\WidowLoanStatus::REJECTED->value,
        ])->exists();

        if ($activeLoan) {
            return false;
        }

        if ($this->is_married) {
            return false;
        }

        return true;
    }

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (!$user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            // ✅ FIXED: Use coordinatedZone instead of zone_id
            $zoneId = $user->coordinatedZone?->id;

            if (!$zoneId) {
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
