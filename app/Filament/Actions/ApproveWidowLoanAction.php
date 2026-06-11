<?php

namespace App\Filament\Actions;

use App\Exceptions\InsufficientBankBalanceException;
use App\Models\WidowLoan;
use App\Services\WidowLoanService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                                'flow'        => $record->approvalFlow,
                                'currentStep' => $record->getCurrentApprovalStep(),
                            ]),
                        Textarea::make('comments')
                            ->label('Approval Comments')
                            ->rows(3)
                            ->placeholder('Add any comments about this approval...')
                            ->columnSpanFull(),

                        TextInput::make('adjusted_principal_amount')
                            ->label('Adjusted Loan Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(1)
                            ->maxValue(fn (WidowLoan $record) => (float) $record->principal_amount)
                            ->default(fn (WidowLoan $record) => (float) $record->principal_amount)
                            ->helperText(fn (WidowLoan $record) =>
                                'Current request: ₦' . number_format((float) $record->principal_amount, 2)
                                . ' | Available for this loan: ₦'
                                . number_format(app(WidowLoanService::class)->availableForLoanApproval($record), 2)
                            )
                            ->visible(fn () => auth()->user()?->hasRole('super_admin')),

                        Textarea::make('amount_adjustment_note')
                            ->label('Amount Adjustment Note')
                            ->rows(3)
                            ->placeholder('State why this loan amount is being reduced.')
                            ->required(fn (callable $get, WidowLoan $record): bool =>
                                auth()->user()?->hasRole('super_admin')
                                && filled($get('adjusted_principal_amount'))
                                && (float) $get('adjusted_principal_amount') !== (float) $record->principal_amount
                            )
                            ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (WidowLoan $record, array $data): void {
                try {
                    $service = app(WidowLoanService::class);

                    if (auth()->user()?->hasRole('super_admin') && filled($data['adjusted_principal_amount'] ?? null)) {
                        $adjustedAmount = (float) $data['adjusted_principal_amount'];

                        if ($adjustedAmount !== (float) $record->principal_amount) {
                            $service->adjustPendingLoanAmount(
                                $record,
                                $adjustedAmount,
                                $data['amount_adjustment_note'] ?? '',
                                auth()->id(),
                            );

                            $record->refresh();
                        }
                    }

                    $service->ensureApprovalFundsAvailable($record);

                    $record->approve($data['comments'] ?? '');

                    Notification::make()
                        ->success()
                        ->title('Loan Approved')
                        ->body("Widow loan for {$record->widow->full_name} has been approved. You may now disburse the funds.")
                        ->send();
                } catch (InsufficientBankBalanceException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Insufficient Disbursement Funds')
                        ->body($e->getMessage())
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Approval Failed')
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->visible(fn (WidowLoan $record) =>
                // The loan must have a pending approval flow
                $record->isAwaitingApproval()
                && (
                    // Super admin always can approve (Gate::before bypass applies too)
                    auth()->user()->hasAnyRole(['super_admin', 'admin'])
                    || auth()->user()->can('approve_widow_loans')
                )
            );
    }
}
