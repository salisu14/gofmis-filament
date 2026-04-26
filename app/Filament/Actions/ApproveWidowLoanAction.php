<?php

namespace App\Filament\Actions;

use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

class ApproveWidowLoanAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label('Approve Loan')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->schema([
                Section::make('Approval Details')
                    ->schema([
                        View::make('filament.components.approval-flow-info')
                            ->viewData(fn (WidowLoan $record) => [
                                'flow' => $record->approvalFlow,
                                'currentStep' => $record->getCurrentApprovalStep(),
                            ]),
                        Textarea::make('comments')
                            ->label('Approval Comments')
                            ->rows(3)
                            ->placeholder('Add any comments about this approval...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (WidowLoan $record, array $data): void {
                $record->approve($data['comments'] ?? '');

                Notification::make()
                    ->success()
                    ->title('Loan Approved')
                    ->body("Widow loan for {$record->widow->full_name} has been approved.")
                    ->send();
            })
            ->visible(fn (WidowLoan $record) => 
                $record->isAwaitingApproval() && 
                auth()->user()->can('approve_widow_loans')
            );
    }
}

