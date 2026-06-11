<?php

namespace App\Filament\Actions;

use App\Models\InterventionRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class StartInterventionRequestReviewAction
{
    public static function make(): Action
    {
        return Action::make('startReview')
            ->label('Start Review')
            ->icon('heroicon-m-eye')
            ->color('info')
            ->requiresConfirmation()
            ->visible(fn (InterventionRequest $record): bool =>
                $record->canStartReview()
                && (auth()->user()?->hasAnyRole(['admin', 'super_admin'])
                    || auth()->user()?->can('verify_education_interventions')
                    || auth()->user()?->can('approve_healthcare_interventions')
                    || auth()->user()?->can('approve_welfare_interventions'))
            )
            ->action(function (InterventionRequest $record): void {
                $record->startReview(auth()->id());

                Notification::make()
                    ->success()
                    ->title('Request moved to review')
                    ->send();
            });
    }
}
