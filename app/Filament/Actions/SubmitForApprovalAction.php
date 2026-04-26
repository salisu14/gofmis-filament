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
                    ->description('This will initiate the approval workflow for this loan.')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Submission Notes')
                            ->rows(3)
                            ->placeholder('Add any notes about this submission...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (WidowLoan $record): void {
                // Define approval steps
                $approvers = [
                    ['role' => 'loan_officer'],
                    ['role' => 'finance_manager'],
                    ['role' => 'director'],
                ];

                // Create approval workflow
                $approvalService = app(ApprovalService::class);
                $approvalService->createApprovalWorkflow($record, $approvers);

                // Update loan status to pending
                $record->update(['status' => \App\Enums\WidowLoanStatus::PENDING]);

                Notification::make()
                    ->success()
                    ->title('Loan Submitted for Approval')
                    ->body("Widow loan for {$record->widow->full_name} has been submitted for approval.")
                    ->send();
            })
            ->visible(fn (WidowLoan $record) => 
                $record->status === \App\Enums\WidowLoanStatus::DRAFT && 
                !$record->approvalFlow &&
                auth()->user()->can('submit_widow_loans')
            );
    }
}

