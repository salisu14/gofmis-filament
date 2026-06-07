<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use App\Models\CompanyInformation;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCompanyInformation extends ViewRecord
{
    protected static string $resource = CompanyInformationResource::class;

    public function getRecord(): CompanyInformation
    {
        return CompanyInformation::instance();
    }

    protected function getHeaderActions(): array
    {
        return [

            EditAction::make(),

        ];
    }
}
