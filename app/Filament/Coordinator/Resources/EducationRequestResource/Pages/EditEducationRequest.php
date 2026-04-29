<?php
// app/Filament/Coordinator\Resources\EducationRequestResource/Pages/EditEducationRequest.php

namespace App\Filament\Coordinator\Resources\EducationRequestResource\Pages;

use App\Filament\Coordinator\Resources\EducationRequestResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEducationRequest extends EditRecord
{
    protected static string $resource = EducationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasAnyRole(['admin', 'super_admin'])),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status !== 'pending') {
            $this->halt();

            Notification::make()
                ->title('Cannot Edit')
                ->body('Only pending requests can be edited.')
                ->danger()
                ->send();
        }
    }
}
