<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'state_id'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function towns(): HasMany
    {
        return $this->hasMany(Town::class);
    }
}
