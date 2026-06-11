<?php

namespace App\Filament\Widgets;

use App\Models\EducationFeeInvoice;
use App\Models\EducationFeePayment;
use App\Models\InterventionRequest;
use App\Models\OrphanEducation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EducationOverviewStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_education_interventions') ?? false;
    }

    protected function getStats(): array
    {
        $activeEnrollments = OrphanEducation::query()->where('is_current', true)->count();
        $supportedEnrollments = OrphanEducation::query()
            ->where('is_current', true)
            ->where('is_fee_supported', true)
            ->count();

        $feesInvoiced = EducationFeeInvoice::query()
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $feesPaid = EducationFeePayment::query()->sum('amount');
        $outstanding = max(0, (float) $feesInvoiced - (float) $feesPaid);

        $pendingVerification = InterventionRequest::query()
            ->education()
            ->whereIn('verification_status', ['pending', 'in_progress'])
            ->count();

        return [
            Stat::make('Active Enrollments', number_format($activeEnrollments))
                ->description(number_format($supportedEnrollments).' currently sponsored')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('info'),

            Stat::make('Fees Invoiced', '₦'.number_format((float) $feesInvoiced, 2))
                ->description('Non-cancelled education invoices')
                ->descriptionIcon('heroicon-m-document-currency-dollar')
                ->color('gray'),

            Stat::make('Fees Paid', '₦'.number_format((float) $feesPaid, 2))
                ->description('Recorded school fee payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Outstanding Fees', '₦'.number_format($outstanding, 2))
                ->description(number_format($pendingVerification).' education requests awaiting verification')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($outstanding > 0 ? 'warning' : 'success'),
        ];
    }
}
