<?php

namespace App\Filament\Actions;

use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

class RejectWidowLoanAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Reject Loan')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Section::make('Rejection Details')
                    ->schema([
                        View::make('filament.components.approval-flow-info')
                            ->viewData(fn (WidowLoan $record) => [
                                'flow'        => $record->approvalFlow,
                                'currentStep' => $record->getCurrentApprovalStep(),
                            ]),
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Explain why this loan is being rejected...')
                            ->columnSpanFull(),
                        Textarea::make('comments')
                            ->label('Additional Comments')
                            ->rows(2)
                            ->placeholder('Optional additional comments...')
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (WidowLoan $record, array $data): void {
                $record->reject($data['reason'], $data['comments'] ?? '');

                Notification::make()
                    ->danger()
                    ->title('Loan Rejected')
                    ->body("Widow loan for {$record->widow->full_name} has been rejected.")
                    ->send();
            })
            ->visible(fn (WidowLoan $record) =>
                $record->isAwaitingApproval()
                && (
                    auth()->user()->hasAnyRole(['super_admin', 'admin'])
                    || auth()->user()->can('reject_widow_loans')
                )
            );
    }
}
