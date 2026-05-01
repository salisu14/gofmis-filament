<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    /**
     * Approve a project
     * @throws \Throwable
     */
    public function approveProject(Project $project): void
    {
        DB::transaction(function () use ($project) {

            $this->ensureStatus($project, ProjectStatus::PLANNING);

            $project->update([
                'status' => ProjectStatus::APPROVED,
                'start_date' => now(),
            ]);

            // Prevent duplicate milestones
            if ($project->milestones()->exists()) {
                return;
            }

            $this->createDefaultMilestones($project);
        });
    }

    /**
     * Start project work
     * @throws \Throwable
     */
    public function startProject(Project $project): void
    {
        DB::transaction(function () use ($project) {

            $this->ensureStatus($project, ProjectStatus::APPROVED);

            $project->update([
                'status' => ProjectStatus::IN_PROGRESS,
                'start_date' => $project->start_date ?? now(),
            ]);
        });
    }

    /**
     * Complete project
     * @throws \Throwable
     */
    public function completeProject(Project $project): void
    {
        DB::transaction(function () use ($project) {

            $this->ensureStatus($project, ProjectStatus::IN_PROGRESS);

            // Ensure all milestones are completed
            $incomplete = $project->milestones()
                ->where('status', '!=', 'completed')
                ->exists();

            if ($incomplete) {
                throw ValidationException::withMessages([
                    'milestones' => 'All milestones must be completed before finishing the project.',
                ]);
            }

            $project->update([
                'status' => ProjectStatus::COMPLETED,
                'actual_completion_date' => now(),
            ]);
        });
    }

    /**
     * Put project on hold
     * @throws \Throwable
     */
    public function holdProject(Project $project, string $reason): void
    {
        DB::transaction(function () use ($project, $reason) {

            $project->update([
                'status' => ProjectStatus::ON_HOLD,
                'notes' => trim($project->notes . "\n\n[HOLD " . now()->format('Y-m-d') . "] " . $reason),
            ]);
        });
    }

    /**
     * Resume project from hold
     */
    public function resumeProject(Project $project): void
    {
        if ($project->status !== ProjectStatus::ON_HOLD) {
            throw ValidationException::withMessages([
                'status' => 'Only projects on hold can be resumed.',
            ]);
        }

        $project->update([
            'status' => ProjectStatus::IN_PROGRESS,
        ]);
    }

    /**
     * Create default milestones (idempotent)
     */
    private function createDefaultMilestones(Project $project): void
    {
        $defaults = $this->getMilestoneTemplates($project->type->value);

        $data = collect($defaults)->map(function ($milestone, $index) use ($project) {
            return [
                'project_id' => $project->id,
                'title' => $milestone['title'],
                'description' => $milestone['description'],
                'sort_order' => $index + 1,
                'status' => $index === 0 ? 'in_progress' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        ProjectMilestone::insert($data); // ⚡ bulk insert (faster)
    }

    /**
     * Templates extracted (cleaner + reusable)
     */
    private function getMilestoneTemplates(string $type): array
    {
        return match ($type) {
            'house' => [
                ['title' => 'Site Preparation', 'description' => 'Clear and level land'],
                ['title' => 'Foundation', 'description' => 'Concrete foundation'],
                ['title' => 'Wall Construction', 'description' => 'Walls & structure'],
                ['title' => 'Roofing', 'description' => 'Install roof'],
                ['title' => 'Finishing', 'description' => 'Painting & fittings'],
                ['title' => 'Handover', 'description' => 'Final delivery'],
            ],

            'school', 'mosque', 'church', 'clinic' => [
                ['title' => 'Planning & Permits', 'description' => 'Approvals'],
                ['title' => 'Foundation', 'description' => 'Groundwork'],
                ['title' => 'Structure', 'description' => 'Main building'],
                ['title' => 'Roofing', 'description' => 'Roof'],
                ['title' => 'Interior', 'description' => 'Finishing'],
                ['title' => 'Utilities', 'description' => 'Electrical/plumbing'],
                ['title' => 'Inspection', 'description' => 'Final QA'],
            ],

            'water' => [
                ['title' => 'Survey', 'description' => 'Water analysis'],
                ['title' => 'Drilling', 'description' => 'Borehole'],
                ['title' => 'Pump Installation', 'description' => 'Pump setup'],
                ['title' => 'Distribution', 'description' => 'Tank & pipes'],
                ['title' => 'Testing', 'description' => 'Quality check'],
            ],

            default => [
                ['title' => 'Planning', 'description' => 'Initial phase'],
                ['title' => 'Execution', 'description' => 'Work phase'],
                ['title' => 'Review', 'description' => 'Quality check'],
                ['title' => 'Completion', 'description' => 'Finish'],
            ],
        };
    }

    /**
     * Recalculate budgets (optimized)
     * @throws \Throwable
     */
    public function recalculateBudget(Project $project): void
    {
        DB::transaction(function () use ($project) {

            $project->load(['expenses', 'milestones.expenses']);

            $totalSpent = $project->expenses->sum('amount');

            $project->update([
                'budget_spent' => $totalSpent,
            ]);

            foreach ($project->milestones as $milestone) {
                $milestone->update([
                    'budget_spent' => $milestone->expenses->sum('amount'),
                ]);
            }
        });
    }

    /**
     * Budget health check
     */
    public function checkBudget(Project $project): array
    {
        $allocated = (float) $project->budget_allocated;
        $spent = (float) $project->budget_spent;

        $remaining = $allocated - $spent;

        $percentage = $allocated > 0
            ? ($spent / $allocated) * 100
            : 0;

        return [
            'allocated' => $allocated,
            'spent' => $spent,
            'remaining' => $remaining,
            'percentage_used' => round($percentage, 2),
            'is_over_budget' => $remaining < 0,
            'status' => match (true) {
                $percentage > 100 => 'over_budget',
                $percentage > 80 => 'warning',
                default => 'good',
            },
        ];
    }

    /**
     * Centralized status guard
     */
    private function ensureStatus(Project $project, ProjectStatus $expected): void
    {
        if ($project->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Expected project status [{$expected->value}] but got [{$project->status->value}].",
            ]);
        }
    }
}
