<?php

namespace App\Filament\Widgets;

use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class WidowLoanPortfolioOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // We use the base model query which respects the Zone scope automatically via the global scope
        $baseQuery = WidowLoan::query();

        // 1. TOTAL LOAN PORTFOLIO
        // Sum of principal_amount for loans that have actually been disbursed or completed/defaulted/written_off.
        $totalPortfolioQuery = clone $baseQuery;
        $totalPortfolioQuery->whereIn('status', [
            WidowLoanStatus::DISBURSED,
            WidowLoanStatus::COMPLETED,
            WidowLoanStatus::DEFAULTED,
            WidowLoanStatus::WRITTEN_OFF,
        ]);
        $totalPortfolio = (float) $totalPortfolioQuery->sum('principal_amount');

        // 2. TOTAL REPAID
        // Sum of canonical total_paid across disbursed/completed/etc loans.
        $totalRepaid = (float) (clone $totalPortfolioQuery)->sum('total_paid');

        // 3. OUTSTANDING BALANCE
        // Sum of canonical outstanding_balance for active financial loans.
        // Wait! Should this only include active financial loans? Yes, not completed or written off.
        // Wait, if it's written off, the outstanding balance is usually zeroed or frozen, but the requirement is "active financial loans".
        $activeLoansQuery = clone $baseQuery;
        $activeLoansQuery->where('status', WidowLoanStatus::DISBURSED)->where('fully_repaid', false);
        $outstandingBalance = (float) $activeLoansQuery->sum('outstanding_balance');

        // 4. ACTIVE / OUTSTANDING LOANS
        // Count of loans currently disbursed and not fully repaid.
        $activeLoansCount = $activeLoansQuery->count();

        // 5. FULLY REPAID
        // Count of loans where fully_repaid = true and/or lifecycle status is completed
        $fullyRepaidQuery = clone $baseQuery;
        $fullyRepaidQuery->where(function (Builder $query) {
            $query->where('fully_repaid', true)
                ->orWhere('status', WidowLoanStatus::COMPLETED);
        });
        $fullyRepaidCount = $fullyRepaidQuery->count();

        // 6. LOANS IN ARREARS
        // Count loans whose performance status is overdue, delinquent, or defaulted.
        $arrearsQuery = clone $baseQuery;
        $arrearsQuery->whereIn('status', [WidowLoanStatus::DISBURSED, WidowLoanStatus::DEFAULTED, WidowLoanStatus::WRITTEN_OFF])
            ->whereIn('performance_status', [
                WidowLoanPerformanceStatus::OVERDUE,
                WidowLoanPerformanceStatus::DELINQUENT,
                WidowLoanPerformanceStatus::DEFAULTED,
            ]);
        $arrearsCount = $arrearsQuery->count();

        // 7. OVERDUE AMOUNT
        // Sum of canonical overdue_amount for loans with arrears.
        $overdueAmount = (float) (clone $arrearsQuery)->sum('overdue_amount');

        // 8. REPAYMENT RATIO
        // Total Repaid / Total Payable
        $totalPayable = (float) (clone $totalPortfolioQuery)->sum('total_payable');
        $repaymentRatio = $totalPayable > 0 ? round(($totalRepaid / $totalPayable) * 100, 1) : 0;

        return [
            Stat::make('Total Loan Portfolio', 'NGN '.number_format($totalPortfolio, 2))
                ->description('Cumulative disbursed principal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Total Repaid', 'NGN '.number_format($totalRepaid, 2))
                ->description('Cumulative collected payments')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Outstanding Balance', 'NGN '.number_format($outstandingBalance, 2))
                ->description('Active principal exposure')
                ->descriptionIcon('heroicon-m-scale')
                ->color('warning'),

            Stat::make('Active Loans', number_format($activeLoansCount))
                ->description('Loans currently in repayment')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Fully Repaid', number_format($fullyRepaidCount))
                ->description('Completed loan lifecycles')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Loans in Arrears', number_format($arrearsCount))
                ->description('Overdue, Delinquent, or Defaulted')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Overdue Amount', 'NGN '.number_format($overdueAmount, 2))
                ->description('Total past due arrears')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Repayment Ratio', $repaymentRatio.'%')
                ->description('Total Paid / Total Payable (Cumulative)')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($repaymentRatio >= 80 ? 'success' : ($repaymentRatio >= 50 ? 'warning' : 'danger')),
        ];
    }
}
