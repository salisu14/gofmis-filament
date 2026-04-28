<?php
// app/Filament/Widgets/AgeDistributionChartWidget.php

namespace App\Filament\Widgets;

use App\Models\Orphan;
use Filament\Widgets\ChartWidget;

class AgeDistributionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Orphan Age Distribution';
    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = ['lg' => 2];

    protected function getData(): array
    {
        $ageGroups = [
            '0-5' => Orphan::whereBetween('age', [0, 5])->where('is_eligible', true)->count(),
            '6-10' => Orphan::whereBetween('age', [6, 10])->where('is_eligible', true)->count(),
            '11-15' => Orphan::whereBetween('age', [11, 15])->where('is_eligible', true)->count(),
            '16-17' => Orphan::whereBetween('age', [16, 17])->where('is_eligible', true)->count(),
            '18+' => Orphan::where('age', '>=', 18)->where('is_eligible', true)->count(),
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Orphans by Age Group',
                    'data' => array_values($ageGroups),
                    'backgroundColor' => [
                        '#10B981', // 0-5 - green
                        '#3B82F6', // 6-10 - blue
                        '#F59E0B', // 11-15 - yellow
                        '#EF4444', // 16-17 - red
                        '#7C3AED', // 18+ - purple
                    ],
                ],
            ],
            'labels' => array_keys($ageGroups),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
