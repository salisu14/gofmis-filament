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
        static::creating(function (Sponsorship $sponsorship) {
            if ($sponsorship->sponsor_id && empty($sponsorship->sponsor_name)) {
                $sponsorship->sponsor_name = Sponsor::find($sponsorship->sponsor_id)?->name;
            }
        });
    }
}
