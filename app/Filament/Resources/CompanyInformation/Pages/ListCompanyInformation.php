<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanyInformation extends ListRecords
{
    protected static string $resource = CompanyInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
