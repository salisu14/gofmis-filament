<?php
// app/Models/ProjectMilestone.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'budget_allocated',
        'budget_spent',
        'due_date',
        'completed_date',
        'sort_order',
    ];

    protected $casts = [
        'budget_allocated' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'due_date' => 'date',
        'completed_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class, 'milestone_id');
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
