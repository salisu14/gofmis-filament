<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Tables;

use App\Models\BankAccount;
use App\Models\OutOfPocketExpenditure;
use App\Models\User;
use App\Services\OutOfPocketExpenditureService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OutOfPocketExpendituresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('expenditure_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('incurredBy.name')
                    ->label('Incurred By')
                    ->searchable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn (string $state) => ucfirst(str_replace('_', ' ', $state)))
                    ->badge(),

                TextColumn::make('amount')
                    ->label('Amount (₦)')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('approval_status')
                    ->label('Approval Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'submitted' => 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('reimbursement_status')
                    ->label('Reimbursement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reimbursed' => 'success',
                        'pending' => 'warning',
                        'not_required' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('approval_status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('reimbursement_status')
                    ->options([
                        'pending' => 'Pending Reimbursement',
                        'reimbursed' => 'Reimbursed',
                        'not_required' => 'Not Required',
                    ]),

                SelectFilter::make('incurred_by_user_id')
                    ->label('Incurred By')
                    ->options(User::query()->pluck('name', 'id')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (OutOfPocketExpenditure $record) => $record->isDraft()),

                ActionGroup::make([
                    Action::make('submit')
                        ->label('Submit for Review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (OutOfPocketExpenditure $record) => $record->isDraft())
                        ->action(function (OutOfPocketExpenditure $record) {
                            app(OutOfPocketExpenditureService::class)->submit($record, auth()->user());
                            Notification::make()
                                ->title('Submitted')
                                ->body('Out of pocket expenditure submitted for review.')
                                ->success()
                                ->send();
                        }),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (OutOfPocketExpenditure $record) => $record->isSubmitted() && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()))
                        ->action(function (OutOfPocketExpenditure $record) {
                            app(OutOfPocketExpenditureService::class)->approve($record, auth()->user());
                            Notification::make()
                                ->title('Approved')
                                ->body('Out of pocket expenditure approved.')
                                ->success()
                                ->send();
                        }),

                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required(),
                        ])
                        ->visible(fn (OutOfPocketExpenditure $record) => $record->isSubmitted() && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()))
                        ->action(function (OutOfPocketExpenditure $record, array $data) {
                            app(OutOfPocketExpenditureService::class)->reject($record, auth()->user(), $data['rejection_reason']);
                            Notification::make()
                                ->title('Rejected')
                                ->body('Out of pocket expenditure rejected.')
                                ->warning()
                                ->send();
                        }),

                    Action::make('reimburse')
                        ->label('Post Reimbursement')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->form([
                            Select::make('bank_account_id')
                                ->label('Foundation Bank Account')
                                ->options(function (OutOfPocketExpenditure $record) {
                                    return BankAccount::getEligibleForOutOfPocketReimbursement((float) $record->amount)
                                        ->mapWithKeys(fn (BankAccount $account) => [
                                            $account->id => "{$account->account_name} — {$account->account_number} — Available: ₦".number_format($account->ledger_balance - $account->reserved_balance, 2),
                                        ]);
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Select an eligible Foundation bank account...')
                                ->helperText(function (OutOfPocketExpenditure $record) {
                                    $count = BankAccount::getEligibleForOutOfPocketReimbursement((float) $record->amount)->count();
                                    if ($count === 0) {
                                        return 'No eligible General/Operating account with sufficient available funds is available for this reimbursement.';
                                    }

                                    return 'Select the Foundation bank account to debit for this reimbursement.';
                                }),
                        ])
                        ->visible(fn (OutOfPocketExpenditure $record) => $record->isPendingReimbursement() && (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()))
                        ->action(function (OutOfPocketExpenditure $record, array $data) {
                            $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
                            app(OutOfPocketExpenditureService::class)->reimburse($record, $bankAccount, auth()->user());
                            Notification::make()
                                ->title('Reimbursed')
                                ->body('Reimbursement posted successfully.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('expenditure_date', 'desc');
    }
}
