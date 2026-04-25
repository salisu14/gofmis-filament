<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterventionType extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }
}
