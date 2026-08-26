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

    public function requiresItems(): bool
    {
        $name = strtolower((string) $this->name);

        return str_contains($name, 'uniform')
            || str_contains($name, 'book')
            || str_contains($name, 'material')
            || str_contains($name, 'stationery')
            || str_contains($name, 'package');
    }

    public function supportsItems(): bool
    {
        $name = strtolower((string) $this->name);

        return $this->requiresItems()
            || str_contains($name, 'equipment')
            || str_contains($name, 'supplies')
            || str_contains($name, 'support');
    }
}
