<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VocationalSkill extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function orphans(): BelongsToMany
    {
        return $this->belongsToMany(Orphan::class, 'orphan_vocational_skill')
            ->withPivot('specify')
            ->withTimestamps();
    }
}
