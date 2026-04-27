<?php

namespace App\Models;

use App\Enums\SponsorType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sponsor extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'email',
        'phone',
        'address',
        'notes',
    ];

    protected $casts = [
        'type' => SponsorType::class,
    ];

    /**
     * Get the sponsorships associated with this sponsor.
     */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /**
     * Get the allocations associated with this sponsor.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SponsorshipAllocation::class);
    }
}
