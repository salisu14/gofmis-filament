<?php
// app/Filament/Widgets/FinancialOverviewWidget.php

namespace App\Filament\Widgets;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Prescription;
use App\Models\Widow;
use App\Models\WidowLoan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinancialOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalLoanCollected = WidowLoan::whereIn('status', ['approved', 'completed', 'active'])->sum('principal_amount');
        $totalLoanRepaid = WidowLoan::where('status', 'completed')->sum('total_paid') ?? 0;

        // Education support costs
        $totalEducationSupport = OrphanEducation::where('is_fee_supported', true)->sum('support_amount');

        // Healthcare costs (prescriptions)
//        $totalHealthcareCost = Prescription::sum('total_cost');
        $totalHealthcareCost = Prescription::sum(
            DB::raw('COALESCE(lab_test_cost, 0) + COALESCE(drug_cost, 0)')
        );

        return [
            Stat::make('Education Support', '₦' . number_format($totalEducationSupport, 2))
                ->description('Total fee support disbursed')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Healthcare Costs', '₦' . number_format($totalHealthcareCost, 2))
                ->description('Total prescription & medical costs')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),

            Stat::make('Total Loans Collected', '₦' . number_format($totalLoanCollected, 2))
                ->description('Repaid: ₦' . number_format($totalLoanRepaid, 2))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Deceased Records', Deceased::count())
                ->description('Family heads registered')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('gray'),

            Stat::make('Widows', Widow::where('is_eligible', true)->count())
                ->description('Eligible beneficiaries')
                ->descriptionIcon('heroicon-m-heart')
                ->color('warning'),

            Stat::make('Orphans', Orphan::where('is_eligible', true)->count())
                ->description('Eligible beneficiaries')
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
