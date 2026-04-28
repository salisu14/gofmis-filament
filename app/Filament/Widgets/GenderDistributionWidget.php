<?php
// app/Filament/Widgets/GenderDistributionWidget.php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Widgets\ChartWidget;

class GenderDistributionWidget extends ChartWidget
{
    protected ?string $heading = 'Gender Distribution';
    protected static ?int $sort = 8;
    protected int|string|array $columnSpan = ['lg' => 2];

    protected function getData(): array
    {
        $maleOrphans = Orphan::where('gender', Gender::MALE)->where('is_eligible', true)->count();
        $femaleOrphans = Orphan::where('gender', Gender::FEMALE)->where('is_eligible', true)->count();

        // Widows are typically female, but check if you track male
        $femaleWidows = Widow::where('is_eligible', true)->count(); // Assuming all widows are female

        return [
            'datasets' => [
                [
                    'label' => 'Orphans',
                    'data' => [$maleOrphans, $femaleOrphans],
                    'backgroundColor' => ['#3B82F6', '#EC4899'],
                ],
                [
                    'label' => 'Widows',
                    'data' => [0, $femaleWidows],
                    'backgroundColor' => ['#9CA3AF', '#F59E0B'],
                ],
            ],
            'labels' => ['Male', 'Female'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
