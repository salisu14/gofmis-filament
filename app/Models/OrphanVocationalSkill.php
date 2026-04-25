<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OrphanVocationalSkill extends Pivot
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orphan_vocational_skills';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'orphan_id',
        'vocational_skill_id',
        'specify',
    ];

    /**
     * Get the orphan that owns the vocational skill record.
     */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /**
     * Get the vocational skill associated with the orphan.
     */
    public function vocationalSkill(): BelongsTo
    {
        return $this->belongsTo(VocationalSkill::class);
    }
}
