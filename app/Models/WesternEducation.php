<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WesternEducation extends Model
{
    use HasUuids;

    protected $fillable = ['level', 'school_name', 'class_level', 'qualification'];

    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }
}
