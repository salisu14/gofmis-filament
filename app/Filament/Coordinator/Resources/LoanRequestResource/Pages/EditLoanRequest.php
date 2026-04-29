<?php
// app/Filament/Coordinator/Resources/LoanRequestResource/Pages/EditLoanRequest.php

namespace App\Filament\Coordinator\Resources\LoanRequestResource\Pages;

use App\Filament\Coordinator\Resources\LoanRequestResource;
use App\Enums\WidowLoanStatus;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoanRequest extends EditRecord
{
    protected static string $resource = LoanRequestResource::class;

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
        // Prevent editing if not in draft status
        if ($this->record->status !== WidowLoanStatus::DRAFT) {
            $this->halt();

            Notification::make()
                ->title('Cannot Edit')
                ->body('Only draft loan requests can be edited.')
                ->danger()
                ->send();
        }
    }
}
