<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'address',
        'coordinator_id',
        'town_id', // ✅ make sure this exists
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

    // ==================================================
    // FIX: Add these accessors to populate the dropdowns
    // ==================================================
    public function getStateIdAttribute()
    {
        return $this->town?->city?->state_id;
    }

    public function getCityIdAttribute()
    {
        return $this->town?->city_id;
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function getCoordinatorNameAttribute(): ?string
    {
        return $this->coordinator?->name;
    }

    public function getCoordinatorPhoneAttribute(): ?string
    {
        return $this->coordinator?->phone;
    }

    public function coordinatorHistories(): HasMany
    {
        return $this->hasMany(ZoneCoordinatorHistory::class);
    }
}
