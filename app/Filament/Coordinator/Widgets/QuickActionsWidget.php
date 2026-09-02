<?php

// app/Filament/Coordinator/Widgets/QuickActionsWidget.php

namespace App\Filament\Coordinator\Widgets;

use App\Filament\Coordinator\Resources\DeceasedResource;
use App\Filament\Coordinator\Resources\EducationRequestResource;
use App\Filament\Coordinator\Resources\OrphanResource;
use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Filament\Coordinator\Resources\WidowResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

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
                    'url' => DeceasedResource::getUrl('create', panel: 'coordinator'),
                ],
                [
                    'label' => 'Add Widow',
                    'description' => 'Register new widow',
                    'icon' => 'heroicon-m-heart',
                    'color' => 'warning',
                    'url' => WidowResource::getUrl('create', panel: 'coordinator'),
                ],
                [
                    'label' => 'Add Orphan',
                    'description' => 'Register new orphan',
                    'icon' => 'heroicon-m-users',
                    'color' => 'info',
                    'url' => OrphanResource::getUrl('create', panel: 'coordinator'),
                ],
                [
                    'label' => 'Education Request',
                    'description' => 'Submit education request',
                    'icon' => 'heroicon-m-academic-cap',
                    'color' => 'primary',
                    'url' => EducationRequestResource::getUrl('create', panel: 'coordinator'),
                ],
                [
                    'label' => 'Welfare Nomination',
                    'description' => 'Submit welfare nomination',
                    'icon' => 'heroicon-m-gift',
                    'color' => 'success',
                    'url' => WelfareRequestResource::getUrl('create', panel: 'coordinator'),
                ],
            ],
        ];
    }
}
