<?php

namespace App\Filament\Widgets;

use App\Services\ConsolidatedFinancialReportService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConsolidatedFinancialOverviewWidget extends BaseWidget
{
    public array $filters = [];

    public string $activeTab = 'consolidated';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $service = app(ConsolidatedFinancialReportService::class);
        $kpis = $service->getKpis($this->filters, $this->activeTab);

        $netMovement = (float) ($kpis['net_cash_movement'] ?? 0);

        return [
            Stat::make('Total Expenditure', 'NGN '.number_format((float) ($kpis['total_expenditure'] ?? 0), 2))
                ->description('All recorded cash outflows')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Income / Receipts', 'NGN '.number_format((float) ($kpis['income_receipts'] ?? 0), 2))
                ->description('All recorded inflows')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Loan Disbursements', 'NGN '.number_format((float) ($kpis['loan_disbursements'] ?? 0), 2))
                ->description('Widow revolving loan disbursements')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Loan Repayments', 'NGN '.number_format((float) ($kpis['loan_repayments'] ?? 0), 2))
                ->description('Loan repayments received')
                ->descriptionIcon('heroicon-m-receipt-refund')
                ->color('info'),

            Stat::make('Internal Transfers', 'NGN '.number_format((float) ($kpis['internal_transfers'] ?? 0), 2))
                ->description('Transfers between Foundation accounts')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('gray'),

            Stat::make('Project Expenditure', 'NGN '.number_format((float) ($kpis['project_expenditure'] ?? 0), 2))
                ->description('Project-related spending')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('Education Expenditure', 'NGN '.number_format((float) ($kpis['education_expenditure'] ?? 0), 2))
                ->description('School and education support spending')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('purple'),

            Stat::make('Intervention Expenditure', 'NGN '.number_format((float) ($kpis['intervention_expenditure'] ?? 0), 2))
                ->description('Approved intervention disbursements')
                ->descriptionIcon('heroicon-m-heart')
                ->color('teal'),

            Stat::make('Historical Imprest', 'NGN '.number_format((float) ($kpis['historical_imprest_expenditure'] ?? 0), 2))
                ->description('Legacy/deprecated imprest expenditure')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray'),

            Stat::make('Non-Cash Welfare', number_format((int) ($kpis['non_cash_welfare_count'] ?? 0)).' items')
                ->description('Distributed non-cash welfare records')
                ->descriptionIcon('heroicon-m-gift')
                ->color('blue'),

            Stat::make('Net Cash Movement', 'NGN '.number_format($netMovement, 2))
                ->description('Inflows less outflows')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netMovement >= 0 ? 'success' : 'danger'),

            Stat::make('Transaction Count', number_format((int) ($kpis['transaction_count'] ?? 0)))
                ->description('Filtered financial transactions')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('gray'),
        ];
    }
}
