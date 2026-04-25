<?php

namespace App\Models;

use App\Enums\InstitutionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'type', 'address'];

    protected $casts = [
        'type' => InstitutionType::class,
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(OrphanEducation::class);
    }
}
