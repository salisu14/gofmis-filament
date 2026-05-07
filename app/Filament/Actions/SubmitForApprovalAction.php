<?php

namespace App\Filament\Actions;

use App\Models\WidowLoan;
use App\Services\ApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

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
                /*
                 * Single-step approval: the super admin is the sole approver.
                 * This matches the described workflow: coordinator applies,
                 * super admin approves or rejects.
                 */
                $approvers = [
                    ['role' => 'super_admin'],
                ];

                app(\App\Services\WidowLoanService::class)->submitForApproval($record, $approvers);

                Notification::make()
                    ->success()
                    ->title('Loan Submitted for Approval')
                    ->body("Loan for {$record->widow->full_name} has been submitted. Awaiting super admin approval.")
                    ->send();
            })
            ->visible(fn (WidowLoan $record) =>
                $record->status === \App\Enums\WidowLoanStatus::DRAFT
                && !$record->approvalFlow
                && (
                    // Coordinators can submit loans they manage
                    auth()->user()->hasAnyRole(['coordinator', 'admin', 'super_admin'])
                    // Or any user with the explicit permission
                    || auth()->user()->can('submit_widow_loans')
                )
            );
    }
}
