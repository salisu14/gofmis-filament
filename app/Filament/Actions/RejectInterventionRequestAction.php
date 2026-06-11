<?php

namespace App\Filament\Actions;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RejectInterventionRequestAction
{
    public static function make(): Action
    {
        return Action::make('rejectRequest')
            ->label('Reject')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject Intervention Request')
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Reason')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->visible(fn (InterventionRequest $record): bool =>
                $record->canRejectRequest()
                && (auth()->user()?->hasAnyRole(['admin', 'super_admin'])
                    || auth()->user()?->can('verify_education_interventions')
                    || auth()->user()?->can('approve_healthcare_interventions')
                    || auth()->user()?->can('approve_welfare_interventions'))
            )
            ->action(function (InterventionRequest $record, array $data): void {
                try {
                    $record->rejectRequest($data['rejection_reason'], auth()->id());

                    Notification::make()
                        ->success()
                        ->title('Request rejected')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Rejection failed')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
