<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use App\Services\Company\CompanyInformationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCompanyInformation extends CreateRecord
{
    protected static string $resource = CompanyInformationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CompanyInformationService::class)->update($data);
    }
}
