<?php

namespace App\Filament\Resources\EducationFeeInvoices\Pages;

use App\Filament\Resources\EducationFeeInvoices\EducationFeeInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEducationFeeInvoices extends ListRecords
{
    protected static string $resource = EducationFeeInvoiceResource::class;

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
