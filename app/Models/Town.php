<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Town extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'city_id'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // Accessor to get State via City
    public function getStateAttribute()
    {
        return $this->city?->state;
    }
}
