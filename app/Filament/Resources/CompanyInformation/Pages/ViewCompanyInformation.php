<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompanyInformation extends ViewRecord
{
    protected static string $resource = CompanyInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
