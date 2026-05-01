<?php
// app/Models/ProjectExpense.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectExpense extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'milestone_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'receipt_number',
        'receipt_path',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected static function booted(): void
    {
        static::creating(function ($media) {
            if (! $media->recorded_by) {
                $media->recorded_by = auth()->id();
            }
        });
    }
}
