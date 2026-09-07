<?php

namespace App\Filament\Widgets;

use App\Enums\AcademicProgressionDecision;
use App\Filament\Pages\EducationAnalytics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EducationAnalyticsOverviewWidget extends BaseWidget
{
    public ?array $filters = [];

    protected function getColumns(): int|array|null
    {
        return 4;
    }

    protected function getStats(): array
    {
        $baseQuery = EducationAnalytics::buildFilteredQuery($this->filters ?? []);

        $currentCount = (clone $baseQuery)->where('is_current', true)->count();
        $promotionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::PROMOTED)->count();
        $repetitionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::REPEATED)->count();
        $demotionsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::DEMOTED)->count();
        $graduationsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::GRADUATED)->count();
        $transfersCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::TRANSFERRED)->count();
        $withdrawalsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::WITHDRAWN)->count();
        $dropoutsCount = (clone $baseQuery)->where('progression_decision', AcademicProgressionDecision::DROPPED_OUT)->count();

        $p6TransitionsCount = (clone $baseQuery)
            ->whereHas('orphanClass', fn ($q) => $q->where('name', 'LIKE', '%Primary 6%'))
            ->where('progression_decision', AcademicProgressionDecision::PROMOTED)
            ->whereHas('successorEnrollment.orphanClass', fn ($q) => $q->where('name', 'LIKE', '%JSS%1%')->orWhere('name', 'LIKE', '%JSS%I%'))
            ->count();

        $totalSupportCost = (float) (clone $baseQuery)->where('is_fee_supported', true)->sum('support_amount');
        $distinctOrphansCount = (clone $baseQuery)->distinct('orphan_id')->count('orphan_id');
        $avgSupportCost = $distinctOrphansCount > 0 ? $totalSupportCost / $distinctOrphansCount : 0.0;

        return [
            Stat::make('Current Active', number_format($currentCount))
                ->description('Enrolled Students')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),

            Stat::make('Promotions', number_format($promotionsCount))
                ->description('Advanced Next Level')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Repetitions', number_format($repetitionsCount))
                ->description('Repeating Same Class')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),

            Stat::make('Demotions', number_format($demotionsCount))
                ->description('Moved Previous Level')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Graduations', number_format($graduationsCount))
                ->description('Completed SS III')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Transfers', number_format($transfersCount))
                ->description('Changed Institution')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('primary'),

            Stat::make('Withdrawals', number_format($withdrawalsCount))
                ->description('Relocated / Withdrawn')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('warning'),

            Stat::make('Dropouts', number_format($dropoutsCount))
                ->description('Discontinued Support')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('P6 → JSS I Transitions', number_format($p6TransitionsCount))
                ->description('Primary to Secondary')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->color('info'),

            Stat::make('Total Support Cost', '₦'.number_format($totalSupportCost, 2))
                ->description('Foundation Outlay')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Avg Cost / Orphan', '₦'.number_format($avgSupportCost, 2))
                ->description('Per Beneficiary')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('success'),
        ];
    }
}
