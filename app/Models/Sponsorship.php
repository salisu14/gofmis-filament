<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsorship extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'orphan_id',
        'sponsor_id',
        'sponsor_name',
        'amount_committed',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'amount_committed' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the sponsor of this sponsorship.
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * Get the orphan being sponsored.
     */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /**
     * Get the allocations from this sponsorship to educational fees.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SponsorshipAllocation::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Sponsorship $sponsorship) {
            if ($sponsorship->sponsor_id && empty($sponsorship->sponsor_name)) {
                $sponsorship->sponsor_name = Sponsor::find($sponsorship->sponsor_id)?->name;
            }

            if ($sponsorship->end_date && $sponsorship->start_date && $sponsorship->end_date->lt($sponsorship->start_date)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'end_date' => 'Sponsorship expiry date cannot be earlier than effective start date.',
                ]);
            }

            if ($sponsorship->orphan_id) {
                $orphan = Orphan::withoutGlobalScopes()->find($sponsorship->orphan_id);
                if ($orphan) {
                    $status = $orphan->status instanceof \App\Enums\OrphanStatus
                        ? $orphan->status
                        : \App\Enums\OrphanStatus::tryFrom((string) $orphan->status);

                    if (! $orphan->is_eligible || $status === \App\Enums\OrphanStatus::ARCHIVED) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'orphan_id' => 'Archived or non-eligible beneficiaries cannot receive new sponsorships.',
                        ]);
                    }

                    $startDate = $sponsorship->start_date ? $sponsorship->start_date->toDateString() : now()->toDateString();
                    $endDate = $sponsorship->end_date ? $sponsorship->end_date->toDateString() : '2099-12-31';

                    $duplicateQuery = static::query()
                        ->where('orphan_id', $sponsorship->orphan_id)
                        ->where('id', '!=', $sponsorship->id ?? '')
                        ->where(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $endDate)
                                ->where(function ($sub) use ($startDate) {
                                    $sub->whereNull('end_date')
                                        ->orWhere('end_date', '>=', $startDate);
                                });
                        });

                    if ($duplicateQuery->exists()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'orphan_id' => 'This beneficiary already has an active sponsorship during the specified period.',
                        ]);
                    }
                }
            }
        });

        static::deleting(function (Sponsorship $sponsorship) {
            if ($sponsorship->allocations()->exists()) {
                throw new \DomainException('Sponsorships with historical allocations cannot be deleted.');
            }
        });
    }
}
