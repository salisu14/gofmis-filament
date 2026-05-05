<?php

namespace App\Filament\Actions;

use App\Enums\WidowLoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Models\WidowLoan;
use App\Services\WidowLoanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DisburseWidowLoanAction
{
    public static function make(): Action
    {
        return Action::make('disburse')
            ->label('Disburse Loan')
            ->icon('heroicon-m-banknotes')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Confirm Loan Disbursement')
            ->modalDescription(fn (WidowLoan $record) =>
                "You are about to disburse ₦" . number_format($record->principal_amount, 2) .
                " to {$record->widow->full_name} via {$record->bankAccount?->account_name}. " .
                "This will debit the bank account and generate the repayment schedule."
            )
            ->modalSubmitActionLabel('Yes, Disburse Now')
            ->action(function (WidowLoan $record): void {
                try {
                    app(WidowLoanService::class)->disburseLoan($record);

                    Notification::make()
                        ->success()
                        ->title('Loan Disbursed')
                        ->body("₦" . number_format($record->principal_amount, 2) .
                              " has been disbursed to {$record->widow->full_name}. Repayment schedule generated.")
                        ->send();
                } catch (InsufficientBankBalanceException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Funds')
                        ->body($e->getMessage())
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Disbursement Failed')
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (WidowLoan $record) =>
                $record->canDisburse() &&
                auth()->user()->can('disburse_widow_loans')
            );
    }
}
