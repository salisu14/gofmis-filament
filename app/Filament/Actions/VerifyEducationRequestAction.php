<?php

namespace App\Filament\Actions;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class VerifyEducationRequestAction
{
    public static function make(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon('heroicon-m-check-badge')
            ->color('info')
            ->visible(fn (InterventionRequest $record): bool => $record->isEducationRequest()
                && in_array($record->status, ['pending', 'under_review'], true)
                && $record->verification_status !== 'verified'
                && (auth()->user()?->hasAnyRole(['admin', 'super_admin'])
                    || auth()->user()?->can('verify_education_interventions'))
            )
            ->modalHeading('Verify Education Request')
            ->modalDescription(fn (InterventionRequest $record): string => "Record verification findings for {$record->orphan?->full_name}.")
            ->schema([
                Textarea::make('verification_notes')
                    ->label('Verification Audit Notes')
                    ->placeholder('e.g. Verified with school principal, receipt authenticity confirmed...')
                    ->required()
                    ->rows(4),

                FileUpload::make('verification_documents')
                    ->label('Supporting Evidence')
                    ->multiple()
                    ->directory('education-verifications')
                    ->disk('public')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/*']),
            ])
            ->action(function (InterventionRequest $record, array $data): void {
                try {
                    $record->markVerified(auth()->id(), $data['verification_notes']);

                    if (! empty($data['verification_documents'])) {
                        $record->update(['verification_documents' => $data['verification_documents']]);
                    }

                    Notification::make()
                        ->title('Request Verified')
                        ->body("Education request for {$record->orphan?->full_name} has been marked as verified.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Verification Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
