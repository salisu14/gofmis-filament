<?php
// app/Filament/Widgets/StatsOverviewWidget.php

namespace App\Filament\Widgets;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = ['lg' => 2]; // Takes half width = 4 stats fit nicely

    protected function getStats(): array
    {
        return [
            Stat::make('Total Deceased', Deceased::count())
                ->description('Registered family heads')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('gray'),

            Stat::make('Total Widows', Widow::where('is_eligible', true)->count())
                ->description(Widow::where('is_married', true)->count() . ' remarried')
                ->descriptionIcon('heroicon-m-heart')
                ->color('warning'),

            Stat::make('Total Orphans', Orphan::where('is_eligible', true)->count())
                ->description(Orphan::where('is_married', true)->count() . ' married')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Total Beneficiaries',
                Widow::where('is_eligible', true)->count() + Orphan::where('is_eligible', true)->count())
                ->description('Widows + Orphans')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
        ];
    }
}
//<?php
//// app/Filament/Widgets/StatsOverviewWidget.php
//
//namespace App\Filament\Widgets;
//
//use App\Models\Deceased;
//use App\Models\Orphan;
//use App\Models\Widow;
//use App\Models\WidowLoan;
//use Filament\Widgets\StatsOverviewWidget as BaseWidget;
//use Filament\Widgets\StatsOverviewWidget\Stat;
//use Illuminate\Database\Eloquent\Builder;
//
//class StatsOverviewWidget extends BaseWidget
//{
//    protected static ?int $sort = 1;
//    protected int|string|array $columnSpan = 'full';
//
//    protected function getStats(): array
//    {
//        return [
//            Stat::make('Total Deceased', Deceased::count())
//                ->description('Registered family heads')
//                ->descriptionIcon('heroicon-m-user-minus')
//                ->color('gray')
//                ->chart([Deceased::whereMonth('created_at', now()->subMonths(2))->count(),
//                    Deceased::whereMonth('created_at', now()->subMonth())->count(),
//                    Deceased::whereMonth('created_at', now()->month)->count()]),
//
//            Stat::make('Total Widows', Widow::where('is_eligible', true)->count())
//                ->description(Widow::where('is_married', true)->count() . ' remarried')
//                ->descriptionIcon('heroicon-m-heart')
//                ->color('warning'),
//
//            Stat::make('Total Orphans', Orphan::where('is_eligible', true)->count())
//                ->description(Orphan::where('is_married', true)->count() . ' married')
//                ->descriptionIcon('heroicon-m-users')
//                ->color('info'),
//
//            Stat::make('Active Loans', WidowLoan::whereNotIn('status', ['completed', 'rejected'])->count())
//                ->description('Total disbursed: ₦' . number_format(WidowLoan::sum('principal_amount'), 2))
//                ->descriptionIcon('heroicon-m-banknotes')
//                ->color('success'),
//        ];
//    }
//}
