<?php

namespace App\Filament\Widgets;

use App\Models\InterventionRequest;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Widow;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HealthcareBeneficiariesStatsWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_medicals') ?? false;
    }

    protected function getStats(): array
    {
        $orphanPrescriptionIds = Prescription::query()
            ->where('prescribable_type', Orphan::class)
            ->distinct()
            ->pluck('prescribable_id')
            ->all();

        $orphanRequestIds = InterventionRequest::query()
            ->whereHas('type', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%health%']))
            ->whereNotNull('orphan_id')
            ->distinct()
            ->pluck('orphan_id')
            ->all();

        $orphanBeneficiaries = count(array_unique(array_merge($orphanPrescriptionIds, $orphanRequestIds)));

        $widowBeneficiaries = Prescription::query()
            ->where('prescribable_type', Widow::class)
            ->distinct('prescribable_id')
            ->count('prescribable_id');

        $totalBeneficiaries = $orphanBeneficiaries + $widowBeneficiaries;

        return [
            Stat::make('Healthcare Orphans', number_format($orphanBeneficiaries))
                ->description('Distinct orphans with health requests or prescriptions')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Healthcare Widows', number_format($widowBeneficiaries))
                ->description('Distinct widows with prescriptions')
                ->descriptionIcon('heroicon-m-heart')
                ->color('warning'),

            Stat::make('Healthcare Beneficiaries', number_format($totalBeneficiaries))
                ->description('Widows + orphans reached')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),
        ];
    }
}
