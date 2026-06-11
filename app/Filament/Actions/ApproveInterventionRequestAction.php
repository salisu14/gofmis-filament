<?php

namespace App\Filament\Actions;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ApproveInterventionRequestAction
{
    public static function make(): Action
    {
        return Action::make('approveRequest')
            ->label('Approve')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Intervention Request')
            ->modalDescription(fn (InterventionRequest $record): string => $record->isEducationRequest()
                ? 'Approve this verified education intervention request.'
                : 'Approve this intervention request for fulfillment.'
            )
            ->visible(fn (InterventionRequest $record): bool =>
                $record->canApproveRequest()
                && (auth()->user()?->hasAnyRole(['admin', 'super_admin'])
                    || auth()->user()?->can('verify_education_interventions')
                    || auth()->user()?->can('approve_healthcare_interventions')
                    || auth()->user()?->can('approve_welfare_interventions'))
            )
            ->action(function (InterventionRequest $record): void {
                try {
                    $record->approveRequest(auth()->id());

                    Notification::make()
                        ->success()
                        ->title('Request approved')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Approval failed')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}
