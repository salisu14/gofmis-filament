<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    // Indirect relationship to towns
    public function towns()
    {
        return $this->hasManyThrough(Town::class, City::class);
    }
}
