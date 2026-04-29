<?php
// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/EditWelfareRequest.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Enums\BeneficiaryStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWelfareRequest extends EditRecord
{
    protected static string $resource = WelfareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasRole(['admin', 'super-admin'])),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status !== BeneficiaryStatus::PENDING) {
            $this->halt();

            Notification::make()
                ->title('Cannot Edit')
                ->body('Only pending requests can be edited.')
                ->danger()
                ->send();
        }
    }
}
