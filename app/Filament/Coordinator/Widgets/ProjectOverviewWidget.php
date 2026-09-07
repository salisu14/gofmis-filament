<?php

// app/Filament/Coordinator/Widgets/ProjectOverviewWidget.php

namespace App\Filament\Coordinator\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasAnyRole(['admin', 'super_admin']);

        $baseQuery = Project::query();

        // Filter by zone for non-admin coordinators
        if (! $isAdmin) {
            if ($zoneId) {
                $baseQuery->where('zone_id', $zoneId);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
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

            Stat::make('Total Budget', '₦'.number_format($baseQuery->clone()->sum('budget_allocated'), 2))
                ->icon('heroicon-m-banknotes')
                ->color('info'),
        ];
    }
}
