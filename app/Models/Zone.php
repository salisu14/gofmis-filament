<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Zone extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'coordinator_name',
        'coordinator_phone'
    ];

    /**
     * A zone contains many deceased records.
     */
    public function deceased(): HasMany
    {
        return $this->hasMany(Deceased::class);
    }

    /**
     * Helper to get full location string.
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    // Dynamic Accessors for reporting convenience
    public function getCityAttribute()
    {
        return $this->town?->city;
    }

    public function getStateAttribute()
    {
        return $this->town?->city?->state;
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'zone_id');
    }
}
