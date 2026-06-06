<?php

namespace App\Filament\Resources\CompanyInformation\Pages;

use App\Filament\Resources\CompanyInformation\CompanyInformationResource;
use App\Services\Company\CompanyInformationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompanyInformation extends EditRecord
{
    protected static string $resource = CompanyInformationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_logo')
                ->label('View Logo')
                ->icon('heroicon-m-eye')
                ->visible(fn () => $this->record->logo_path !== null)
                ->url(fn () => $this->record->logo_url, true),

            Action::make('preview_documents')
                ->label('Preview Document Header')
                ->icon('heroicon-m-document-text')
                ->modalHeading('Document Preview')
                ->modalContent(fn () => view('filament.components.company-document-preview', [
                    'company' => $this->record,
                ])),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(CompanyInformationService::class)->updateRecord($record, $data);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Company information updated')
            ->body('Your company details have been saved successfully.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record->id]);
    }
}
