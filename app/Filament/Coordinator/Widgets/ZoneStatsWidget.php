<?php
// app/Filament/Coordinator/Widgets/ZoneStatsWidget.php

namespace App\Filament\Coordinator\Widgets;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Widow;
use App\Models\WidowLoan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ZoneStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();
        $zoneId = $user->zone_id;
        $zoneName = $user->zone?->name ?? 'Unknown Zone';

        return [
            Stat::make($zoneName, 'Your Zone')
                ->description($user->name)
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary')
                ->extraAttributes(['class' => 'col-span-2']),

            Stat::make('Families', number_format(Deceased::where('zone_id', $zoneId)->count()))
                ->description('Registered deceased')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('gray'),

            Stat::make('Orphans', number_format(
                Orphan::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                    ->where('is_eligible', true)->count()
            ))
                ->description('Eligible')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Widows', number_format(
                Widow::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                    ->where('is_eligible', true)->count()
            ))
                ->description('Eligible')
                ->descriptionIcon('heroicon-m-heart')
                ->color('warning'),

            Stat::make('Active Loans', number_format(
                WidowLoan::whereHas('widow', fn($q) => $q->whereHas('deceased', fn($q2) =>
                $q2->where('zone_id', $zoneId)
                ))->whereIn('status', ['approved', 'disbursed'])->count()
            ))
                ->description('In your zone')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
