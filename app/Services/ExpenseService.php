<?php
// app/Services/ExpenseService.php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    /**
     * Record a new expense and update project/milestone budgets
     * @throws \Throwable
     */
    public function recordExpense(array $data, string $recordedBy): ProjectExpense
    {
        return DB::transaction(function () use ($data, $recordedBy) {
            $expense = ProjectExpense::create([
                'project_id' => $data['project_id'],
                'milestone_id' => $data['milestone_id'] ?? null,
                'category' => $data['category'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'receipt_number' => $data['receipt_number'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'recorded_by' => $recordedBy,
                'notes' => $data['notes'] ?? null,
            ]);

            // Recalculate project budget
            $project = Project::find($data['project_id']);
            app(ProjectService::class)->recalculateBudget($project);

            return $expense;
        });
    }

    /**
     * Delete expense and recalculate
     */
    public function deleteExpense(ProjectExpense $expense): void
    {
        $project = $expense->project;

        // Delete receipt file if exists
        if ($expense->receipt_path && Storage::exists($expense->receipt_path)) {
            Storage::delete($expense->receipt_path);
        }

        $expense->delete();

        // Recalculate
        app(ProjectService::class)->recalculateBudget($project);
    }

    /**
     * Get expense summary by category
     */
    public function getCategorySummary(Project $project): array
    {
        return $project->expenses()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();
    }

    /**
     * Get monthly expense trend
     */
    public function getMonthlyTrend(Project $project): array
    {
        return $project->expenses()
            ->selectRaw("DATE_TRUNC('month', expense_date) as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($row) => [
                'month' => \Carbon\Carbon::parse($row->month)->format('M Y'),
                'total' => (float) $row->total,
            ])
            ->toArray();
    }

    /**
     * Recalculate project budget spent
     */
    public function recalculateBudget(Project $project): void
    {
        $totalSpent = $project->expenses()->sum('amount');
        $project->update(['budget_spent' => $totalSpent]);

        // Also recalculate milestone budgets
        foreach ($project->milestones as $milestone) {
            $milestoneSpent = $milestone->expenses()->sum('amount');
            $milestone->update(['budget_spent' => $milestoneSpent]);
        }
    }

    /**
     * Check if project is over budget
     */
    public function checkBudget(Project $project): array
    {
        $remaining = $project->budget_allocated - $project->budget_spent;
        $percentageUsed = $project->budget_allocated > 0
            ? ($project->budget_spent / $project->budget_allocated) * 100
            : 0;

        return [
            'allocated' => (float) $project->budget_allocated,
            'spent' => (float) $project->budget_spent,
            'remaining' => $remaining,
            'percentage_used' => $percentageUsed,
            'is_over_budget' => $remaining < 0,
            'status' => $percentageUsed > 100 ? 'over_budget' :
                ($percentageUsed > 80 ? 'warning' : 'good'),
        ];
    }
}
