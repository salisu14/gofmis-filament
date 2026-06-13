<?php

namespace App\Filament\Widgets;

use App\Models\BankAccount;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankOverviewStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalLedger = (float) BankAccount::query()->sum('ledger_balance');
        $totalReserved = (float) BankAccount::query()->sum('reserved_balance');
        $totalAvailable = $totalLedger - $totalReserved;

        // Calculate total expenditures (all debit types)
        $totalExpenditures = (float) Transaction::query()
            ->whereNotIn('type', ['deposit', 'loan_repayment', 'imprest_replenishment_reversal', 'imprest_expense_void'])
            ->sum('amount');

        return [
            Stat::make('Cumulative Ledger Balance', '₦' . number_format($totalLedger, 2))
                ->description('Total actual cash across all accounts')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),

            Stat::make('Cumulative Reserved Funds', '₦' . number_format($totalReserved, 2))
                ->description('Funds tied up in pending approval flows')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color('warning'),

            Stat::make('Cumulative Available Balance', '₦' . number_format($totalAvailable, 2))
                ->description('Total funds free to be utilized')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($totalAvailable > 0 ? 'success' : 'danger'),

            Stat::make('Total Expenditures', '₦' . number_format($totalExpenditures, 2))
                ->description('Total debits, withdrawals & expenses')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('gray'),
        ];
    }
}
