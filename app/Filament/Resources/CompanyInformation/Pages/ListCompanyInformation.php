<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use App\Models\CompanyInformation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCompanyInformation extends ListRecords
{
    protected static string $resource = CompanyInformationResource::class;

    public function mount(): void
    {
        CompanyInformation::instance();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manage')
                ->label('Manage Company Information')
                ->icon('heroicon-m-pencil-square')
                ->url(fn () => CompanyInformationResource::getUrl('edit', [
                    'record' => CompanyInformation::SINGLETON_ID,
                ])),
        ];
    }
}
