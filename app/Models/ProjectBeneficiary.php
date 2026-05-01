<?php
// app/Models/ProjectBeneficiary.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectBeneficiary extends Model
{
    use HasUuids;

    protected $table = 'project_beneficiaries';

    protected $fillable = [
        'project_id',
        'beneficiary_id',
        'beneficiary_type',
        'role',
        'notes',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }

    public function zone(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Zone::class,
            Project::class,
            'id',           // Foreign key on Project
            'id',           // Foreign key on Zone
            'project_id',  // Local key on ProjectMilestone
            'zone_id'       // Local key on Project
        );
    }
}
