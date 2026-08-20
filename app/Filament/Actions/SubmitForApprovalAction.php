<?php

namespace App\Filament\Actions;

use App\Exceptions\InsufficientBankBalanceException;
use App\Models\WidowLoan;
use App\Services\WidowLoanService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Throwable;

class SubmitForApprovalAction
{
    public static function make(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for Approval')
            ->icon('heroicon-m-paper-airplane')
            ->color('info')
            ->requiresConfirmation()
            ->schema([
                Section::make('Submit Loan for Approval')
                    ->description('This will send the loan application to the super admin for review.')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Submission Notes')
                            ->rows(3)
                            ->placeholder('Add any notes about this submission...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (WidowLoan $record): void {
                $approvers = [
                    ['role' => 'super_admin'],
                ];

                try {
                    app(WidowLoanService::class)
                        ->submitForApproval($record, $approvers);

                    Notification::make()
                        ->success()
                        ->title('Loan Submitted for Approval')
                        ->body(
                            "Loan for {$record->widow->full_name} has been submitted. ".
                            'Awaiting super admin approval.'
                        )
                        ->send();

                } catch (InsufficientBankBalanceException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Disbursement Funds')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;

                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title('Unable to Submit Loan')
                        ->body(
                            'The loan could not be submitted for approval. '.
                            'Please try again or contact an administrator.'
                        )
                        ->persistent()
                        ->send();
                }
            })
            ->visible(fn (WidowLoan $record) => $record->status === \App\Enums\WidowLoanStatus::DRAFT
                && ! $record->approvalFlow
                && (
                    auth()->user()->hasAnyRole([
                        'coordinator',
                        'admin',
                        'super_admin',
                    ])
                    || auth()->user()->can('submit_widow_loans')
                )
            );
    }
}
