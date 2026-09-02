<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorshipAllocation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'sponsorship_id',
        'sponsor_id',
        'orphan_education_id',
        'amount_allocated',
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function ($allocation) {
            if (! $allocation->sponsor_id && $allocation->sponsorship_id) {
                $allocation->sponsor_id = $allocation->sponsorship->sponsor_id;
            }
        });

        static::saving(function (SponsorshipAllocation $allocation) {
            if ($allocation->sponsorship_id && $allocation->amount_allocated > 0) {
                $sponsorship = $allocation->sponsorship;
                if ($sponsorship) {
                    $existingAllocated = (float) $sponsorship->allocations()
                        ->where('id', '!=', $allocation->id ?? '')
                        ->sum('amount_allocated');
                    $remaining = (float) $sponsorship->amount_committed - $existingAllocated;

                    if ((float) $allocation->amount_allocated > $remaining + 0.001) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'amount_allocated' => 'Allocation amount (₦'.number_format((float) $allocation->amount_allocated, 2).') exceeds the remaining sponsorship balance (₦'.number_format(max(0, $remaining), 2).').',
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Get the sponsor of this allocation.
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * Get the sponsorship source for this allocation.
     */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    /**
     * Get the education record this allocation is applied to.
     */
    public function education(): BelongsTo
    {
        return $this->belongsTo(OrphanEducation::class, 'orphan_education_id');
    }
}
