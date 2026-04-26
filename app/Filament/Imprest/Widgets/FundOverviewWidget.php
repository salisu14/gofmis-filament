<?php

namespace App\Filament\Imprest\Widgets;

use App\Models\ImprestFund;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FundOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFunds = ImprestFund::active()->count();
        $lowBalanceFunds = ImprestFund::active()->get()->filter->isLowBalance()->count();
        $totalAuthorized = ImprestFund::active()->sum('authorized_amount');
        $totalCurrent = ImprestFund::active()->sum('current_balance');

        return [
            Stat::make('Active Funds', $totalFunds)
                ->description('Operational imprest accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make('Total Authorized', number_format($totalAuthorized, 2))
                ->description('Combined authorized amounts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Available Balance', number_format($totalCurrent, 2))
                ->description(number_format(($totalCurrent / max($totalAuthorized, 1)) * 100, 1) . '% remaining')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($totalCurrent < ($totalAuthorized * 0.3) ? 'danger' : 'success'),

            Stat::make('Low Balance Alerts', $lowBalanceFunds)
                ->description('Funds below 20% threshold')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowBalanceFunds > 0 ? 'warning' : 'success'),
        ];
    }
}
