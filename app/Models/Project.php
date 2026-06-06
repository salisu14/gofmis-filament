<?php
// app/Models/Project.php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Project extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'budget_allocated',
        'budget_spent',
        'zone_id',
        'deceased_id',
        'location_address',
        'coordinator_id',
        'start_date',
        'expected_completion_date',
        'actual_completion_date',
        'notes',
    ];

    protected $casts = [
        'type' => ProjectType::class,
        'status' => ProjectStatus::class,
        'budget_allocated' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'start_date' => 'date',
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function getCoordinatorNameAttribute(): ?string
    {
        return $this->zone?->coordinator?->name;
    }

    public function deceased(): BelongsTo
    {
        return $this->belongsTo(Deceased::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProjectMedia::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(ProjectBeneficiary::class);
    }

    public function orphanBeneficiaries(): MorphToMany
    {
        return $this->morphedByMany(Orphan::class, 'beneficiary', 'project_beneficiaries', 'project_id', 'beneficiary_id')
            ->withPivot('role', 'notes');
    }

    public function widowBeneficiaries(): MorphToMany
    {
        return $this->morphedByMany(Widow::class, 'beneficiary', 'project_beneficiaries', 'project_id', 'beneficiary_id')
            ->withPivot('role', 'notes');
    }

    public function getProgressPercentageAttribute(): int
    {
        $total = $this->milestones()->count();
        if ($total === 0) return 0;

        $completed = $this->milestones()->where('status', 'completed')->count();
        return (int) round(($completed / $total) * 100);
    }

    public function getBudgetRemainingAttribute(): float
    {
        return (float) $this->budget_allocated - (float) $this->budget_spent;
    }

    public function getIsOverBudgetAttribute(): bool
    {
        return $this->budget_spent > $this->budget_allocated;
    }

//    public function getActivityLogOptions(): LogOptions
//    {
//        return LogOptions::defaults()
//            ->logOnly(['name', 'status', 'budget_allocated', 'budget_spent', 'zone_id', 'coordinator_id'])
//            ->logOnlyDirty()
//            ->dontSubmitEmptyLogs();
//    }

    protected static function booted(): void
    {
        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            $zoneId = $user->coordinatedZone?->id;

            if (! $zoneId) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('zone_id', $zoneId);
        });
    }
}
