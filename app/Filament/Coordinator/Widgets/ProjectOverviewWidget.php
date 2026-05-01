<?php
// app/Filament/Coordinator/Widgets/ProjectOverviewWidget.php

namespace App\Filament\Coordinator\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasRole(['admin', 'super-admin']);

        $baseQuery = Project::query();

        // Only filter by zone for non-admin coordinators who have a coordinated zone
        if (!$isAdmin && $zoneId) {
            $baseQuery->where('zone_id', $zoneId);
        }

        return [
            Stat::make('Total Projects', $baseQuery->clone()->count())
                ->icon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('In Progress', $baseQuery->clone()->where('status', ProjectStatus::IN_PROGRESS)->count())
                ->icon('heroicon-m-arrow-path')
                ->color('warning'),

            Stat::make('Completed', $baseQuery->clone()->where('status', ProjectStatus::COMPLETED)->count())
                ->icon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Total Budget', '₦' . number_format($baseQuery->clone()->sum('budget_allocated'), 2))
                ->icon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
