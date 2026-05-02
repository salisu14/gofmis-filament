<?php

namespace App\Filament\Resources\Verifications\Pages;


use App\Filament\Resources\Verifications\EducationVerificationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEducationVerification extends ViewRecord
{
    protected static string $resource = EducationVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Verification')
                ->icon('heroicon-m-pencil-square'),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Request')
                ->modalDescription('Confirm approval of this education request.')
                ->visible(fn($record) => in_array($record->status, ['pending', 'under_review']))
                ->action(function ($record) {
                    $record->update([
                        'status' => 'approved',
                        'verification_status' => 'verified',
                        'verified_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Request Approved')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject Request')
                ->modalDescription('Confirm rejection of this education request.')
                ->visible(fn($record) => in_array($record->status, ['pending', 'under_review']))
                ->action(function ($record) {
                    $record->update([
                        'status' => 'rejected',
                        'verification_status' => 'failed',
                        'verified_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Request Rejected')
                        ->danger()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                }),

            DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasRole(['admin', 'super-admin'])),
        ];
    }
}
