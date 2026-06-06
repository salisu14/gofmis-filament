<?php

namespace App\Filament\Actions;

use App\Models\WidowLoan;
use App\Services\WidowLoanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class MarkLoanCollectedAction
{
    public static function make(): Action
    {
        return Action::make('markCollected')
            ->label('Mark as Collected')
            ->icon('heroicon-m-hand-raised')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirm Collection')
            ->modalDescription(fn (WidowLoan $record) =>
                "Confirm that {$record->widow->full_name} has physically received and collected " .
                "the disbursed funds of ₦" . number_format($record->principal_amount, 2) . "."
            )
            ->modalSubmitActionLabel('Confirm Collection')
            ->action(function (WidowLoan $record): void {
                try {
                    app(WidowLoanService::class)->collectLoan($record, auth()->id());

                    Notification::make()
                        ->success()
                        ->title('Loan Marked as Collected')
                        ->body("{$record->widow->full_name} has confirmed receipt of the loan funds. Repayments can now be recorded.")
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Action Failed')
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (WidowLoan $record) =>
                $record->canCollect() &&
                auth()->user()?->can('collect_widow_loans') &&
                (auth()->user()?->hasAnyRole(['admin', 'super_admin']) || auth()->user()?->managesZone($record->widow?->deceased?->zone_id))
            );
    }
}
