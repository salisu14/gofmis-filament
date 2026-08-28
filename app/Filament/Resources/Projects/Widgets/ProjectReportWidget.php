<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectReportWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $projects = Project::get();

        $totalProjects = $projects->count();
        $activeProjects = $projects->where('status', ProjectStatus::IN_PROGRESS)->count();
        $totalBudget = (float) $projects->sum('budget_allocated');
        $totalSpent = (float) \App\Models\ProjectExpense::whereIn('project_id', $projects->pluck('id'))->sum('amount');

        return [
            Stat::make('Total Projects', $totalProjects)
                ->description('All projects registered')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),
            Stat::make('Active Projects', $activeProjects)
                ->description('Projects currently in progress')
                ->descriptionIcon('heroicon-m-play')
                ->color('info'),
            Stat::make('Total Budget Allocated', '₦'.number_format($totalBudget, 2))
                ->description('Overall budget allocated')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Total Budget Spent', '₦'.number_format($totalSpent, 2))
                ->description('Overall budget spent')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color($totalSpent > $totalBudget ? 'danger' : 'warning'),
        ];
    }
}
