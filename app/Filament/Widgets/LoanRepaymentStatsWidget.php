<?php
// app/Filament/Widgets/LoanRepaymentStatsWidget.php

namespace App\Filament\Widgets;

use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoanRepaymentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalRepaid = WidowLoanRepayment::sum('amount');
        $totalRepaidThisMonth = WidowLoanRepayment::whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');
        $totalRepaidLastMonth = WidowLoanRepayment::whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('amount');

        $repaymentCount = WidowLoanRepayment::count();
        $avgRepayment = $repaymentCount > 0 ? $totalRepaid / $repaymentCount : 0;

        $totalPrincipal = WidowLoan::whereIn('status', ['approved', 'completed', 'disbursed'])->sum('principal_amount');
        $totalPaidViaLoan = WidowLoan::sum('total_paid');
        $repaymentRate = $totalPrincipal > 0 ? ($totalPaidViaLoan / $totalPrincipal) * 100 : 0;

        return [
            Stat::make('Total Repaid', '₦' . number_format((float) $totalRepaid, 2))
                ->description('All time repayments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([
                    (float) WidowLoanRepayment::whereMonth('paid_at', now()->subMonths(2)->month)->sum('amount'),
                    (float) WidowLoanRepayment::whereMonth('paid_at', now()->subMonth()->month)->sum('amount'),
                    (float) $totalRepaidThisMonth,
                ]),

            Stat::make('This Month', '₦' . number_format((float) $totalRepaidThisMonth, 2))
                ->description(
                    $totalRepaidLastMonth > 0
                        ? (($totalRepaidThisMonth > $totalRepaidLastMonth ? '+' : '') .
                        number_format((($totalRepaidThisMonth - $totalRepaidLastMonth) / $totalRepaidLastMonth) * 100, 1) . '% vs last month')
                        : 'No data last month'
                )
                ->descriptionIcon('heroicon-m-calendar')
                ->color($totalRepaidThisMonth >= $totalRepaidLastMonth ? 'success' : 'warning'),

            Stat::make('Repayment Rate', number_format($repaymentRate, 1) . '%')
                ->description('₦' . number_format((float) $totalPaidViaLoan, 2) . ' of ₦' . number_format((float) $totalPrincipal, 2))
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($repaymentRate >= 75 ? 'success' : ($repaymentRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Avg. Repayment', '₦' . number_format((float) $avgRepayment, 2))
                ->description($repaymentCount . ' total repayments')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Active Loans', WidowLoan::whereIn('status', ['approved', 'disbursed'])->count())
                ->description('With outstanding balance')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make('Fully Repaid', WidowLoan::where('fully_repaid', true)->count())
                ->description('Loans completed')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
