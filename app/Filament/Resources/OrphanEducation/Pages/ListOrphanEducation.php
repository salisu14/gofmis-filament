<?php

namespace App\Filament\Resources\OrphanEducation\Pages;

use App\Filament\Resources\OrphanEducation\OrphanEducationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrphanEducation extends ListRecords
{
    protected static string $resource = OrphanEducationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\EducationOverviewStatsWidget::class,
        ];
    }
}
