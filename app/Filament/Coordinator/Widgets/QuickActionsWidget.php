<?php
// app/Filament/Coordinator/Widgets/QuickActionsWidget.php

namespace App\Filament\Coordinator\Widgets;


use App\Filament\Coordinator\Resources\DeceasedResource;
use App\Filament\Coordinator\Resources\LoanRequestResource;
use App\Filament\Coordinator\Resources\OrphanResource;
use App\Filament\Coordinator\Resources\WidowResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = ['lg' => 2];
    protected string $view = 'filament.coordinator.widgets.quick-actions';

    protected function getViewData(): array
    {
        return [
            'actions' => [
                [
                    'label' => 'Register Deceased',
                    'description' => 'Add new family head',
                    'icon' => 'heroicon-m-user-minus',
                    'color' => 'gray',
                    'url' => DeceasedResource::getUrl('create'),
                ],
                [
                    'label' => 'Add Orphan',
                    'description' => 'Register new orphan',
                    'icon' => 'heroicon-m-users',
                    'color' => 'info',
                    'url' => OrphanResource::getUrl('create'),
                ],
                [
                    'label' => 'Add Widow',
                    'description' => 'Register new widow',
                    'icon' => 'heroicon-m-heart',
                    'color' => 'warning',
                    'url' => WidowResource::getUrl('create'),
                ],
                [
                    'label' => 'Loan Request',
                    'description' => 'Submit loan request',
                    'icon' => 'heroicon-m-banknotes',
                    'color' => 'success',
                    'url' => LoanRequestResource::getUrl('create'),
                ],
            ],
        ];
    }
}
