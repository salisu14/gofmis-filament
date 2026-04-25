<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VocationalSkill extends Model
{
    use HasUuids;

    protected $table = 'vocational_skills';

    protected $fillable = ['name'];

    public function orphanSkills(): BelongsToMany
    {
        return $this->belongsToMany(
            Orphan::class,
            'orphan_vocational_skills',
            'vocational_skill_id',
            'orphan_id'
        )->using(OrphanVocationalSkill::class)
            ->withPivot('specify')
            ->withTimestamps();
    }
}
