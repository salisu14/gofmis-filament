<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Models\BankAccount;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable(),

                TextColumn::make('parent.account_name')
                    ->label('Parent')
                    ->placeholder('Parent account')
                    ->toggleable(),

                TextColumn::make('usage')
                    ->label('Usage')
                    ->formatStateUsing(fn (?string $state): string => BankAccount::usageOptions()[$state] ?? str($state)->headline()->toString())
                    ->badge()
                    ->color(fn (BankAccount $record): string => $record->isSubAccount() ? 'info' : 'success')
                    ->sortable(),

                TextColumn::make('ledger_balance')
                    ->label('Own Balance')
                    ->money('NGN')
                    ->alignEnd(),

                TextColumn::make('consolidated_balance')
                    ->label('Consolidated Balance')
                    ->money('NGN')
                    ->alignEnd()
                    ->weight('bold')
                    ->visible(fn ($record) => $record?->isMainAccount())
                    ->tooltip('Own balance + Sub-accounts balance'),

                TextColumn::make('opening_balance')
                    ->label('Initial Deposit')
                    ->money('NGN')
                    ->sortable()
                    ->color('primary')
                    ->alignEnd(),

                TextColumn::make('ledger_balance')
                    ->label('Ledger')
                    ->money('NGN')
                    ->sortable()
                    ->color('gray')
                    ->alignEnd(),

                TextColumn::make('reserved_balance')
                    ->label('Reserved')
                    ->money('NGN')
                    ->color('warning')
                    ->alignEnd(),

                TextColumn::make('available_balance')
                    ->label('Available')
                    ->state(fn (BankAccount $record) => $record->ledger_balance - $record->reserved_balance)
                    ->money('NGN')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('user.name')
                    ->label('Manager')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Filter by Manager')
                    ->relationship('user', 'name'),

                SelectFilter::make('usage')
                    ->label('Usage')
                    ->options(BankAccount::usageOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('recordDeposit')
                        ->label('Record Deposit')
                        ->icon('heroicon-m-arrow-down-circle')
                        ->color('success')
                        ->visible(fn (BankAccount $record): bool => $record->canPerformManualBankMovement())
                        ->modalHeading('Record External Deposit')
                        ->schema([
                            TextInput::make('amount')
                                ->required()
                                ->numeric()
                                ->prefix('₦')
                                ->minValue(0.01)
                                ->step(0.01),
                            DatePicker::make('date')->default(now())->required(),
                            Textarea::make('description')->required()->placeholder('e.g., Cash donation from XYZ'),
                        ])
                        ->action(function (BankAccount $record, array $data) {
                            Transaction::create([
                                'bank_account_id' => $record->id,
                                'type' => 'deposit',
                                'amount' => $data['amount'],
                                'date' => $data['date'],
                                'description' => $data['description'],
                                'reference' => Transaction::generateReference('deposit'),
                                'is_system' => false,
                            ]);
                        }),

                    // ✅ NEW: Dedicated Transfer Action
                    Action::make('transferFunds')
                        ->label('Transfer Funds')
                        ->icon('heroicon-m-arrow-right-circle')
                        ->color('info')
                        ->visible(fn (BankAccount $record): bool => $record->canPerformManualBankMovement())
                        ->modalHeading('Transfer Funds Between Accounts')
                        ->modalDescription(fn (BankAccount $record) => "Source Account: {$record->account_name} (Balance: ₦".number_format($record->ledger_balance, 2).')')
                        ->requiresConfirmation()
                        ->schema(function (BankAccount $record): array {
                            $availableBalance = (float) $record->ledger_balance - (float) ($record->reserved_balance ?? 0);
                            $hasAvailableFunds = $availableBalance > 0;

                            $amountField = TextInput::make('amount')
                                ->label('Transfer Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->step(0.01);

                            if ($hasAvailableFunds) {
                                $amountField
                                    ->minValue(0.01)
                                    ->maxValue($availableBalance)
                                    ->validationMessages([
                                        'max' => 'Transfer amount cannot exceed the available balance of NGN '.number_format($availableBalance, 2).'.',
                                        'min' => 'Transfer amount must be at least NGN 0.01.',
                                    ])
                                    ->helperText('Available to transfer: ₦'.number_format($availableBalance, 2));
                            } else {
                                $amountField
                                    ->disabled()
                                    ->helperText('This source account has no available funds to transfer.');
                            }

                            return [
                                Select::make('destination_bank_account_id')
                                    ->label('Destination Account')
                                    ->options(fn () => BankAccount::query()
                                        ->whereKeyNot($record->id)
                                        ->orderBy('account_name')
                                        ->get()
                                        ->mapWithKeys(fn (BankAccount $account) => [
                                            $account->id => "{$account->account_name} ({$account->account_number}) - {$account->usage_label}",
                                        ])
                                        ->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                $amountField,

                                DatePicker::make('date')
                                    ->label('Transfer Date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),

                                TextInput::make('reference')
                                    ->label('Reference')
                                    ->default(fn () => Transaction::generateReference('transfer'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('description')
                                    ->label('Reason for Transfer')
                                    ->placeholder('e.g., Moving funds to repayment bucket')
                                    ->required()
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (BankAccount $record, array $data) {
                            try {
                                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                                    $sourceAccount = BankAccount::query()
                                        ->whereKey($record->id)
                                        ->lockForUpdate()
                                        ->firstOrFail();

                                    $availableBalance = (float) $sourceAccount->ledger_balance - (float) ($sourceAccount->reserved_balance ?? 0);
                                    $requestedAmount = (float) $data['amount'];

                                    if ($availableBalance <= 0 || $requestedAmount > $availableBalance) {
                                        throw \Illuminate\Validation\ValidationException::withMessages([
                                            'amount' => 'Transfer amount cannot exceed the available balance of NGN '.number_format(max(0, $availableBalance), 2).'.',
                                        ]);
                                    }

                                    Transaction::create([
                                        'bank_account_id' => $sourceAccount->id,
                                        'destination_bank_account_id' => $data['destination_bank_account_id'],
                                        'type' => 'transfer',
                                        'amount' => $requestedAmount,
                                        'date' => $data['date'],
                                        'reference' => $data['reference'],
                                        'description' => $data['description'],
                                        'is_system' => false,
                                    ]);
                                });

                                Notification::make()
                                    ->title('Transfer Successful')
                                    ->body('Funds have been moved between accounts.')
                                    ->success()
                                    ->send();

                            } catch (\Illuminate\Validation\ValidationException $e) {
                                throw $e;
                            } catch (\App\Exceptions\InsufficientBankBalanceException $e) {
                                Notification::make()
                                    ->title('Insufficient Funds')
                                    ->body('The source account does not have enough balance for this transfer.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('recordWithdrawal')
                        ->label('Record Withdrawal')
                        ->icon('heroicon-m-arrow-up-circle')
                        ->color('danger')
                        ->visible(fn (BankAccount $record): bool => $record->canPerformManualBankMovement())
                        ->modalHeading(fn (BankAccount $record) => "Record Withdrawal from {$record->account_name} (Balance: ₦".number_format($record->ledger_balance, 2).')')
                        ->requiresConfirmation()
                        ->schema(function (BankAccount $record): array {
                            $availableBalance = (float) $record->ledger_balance - (float) ($record->reserved_balance ?? 0);
                            $hasAvailableFunds = $availableBalance > 0;

                            $amountField = TextInput::make('amount')
                                ->label('Withdrawal Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->step(0.01);

                            if ($hasAvailableFunds) {
                                $amountField
                                    ->minValue(0.01)
                                    ->maxValue($availableBalance)
                                    ->validationMessages([
                                        'max' => 'Withdrawal amount cannot exceed the available balance of NGN '.number_format($availableBalance, 2).'.',
                                        'min' => 'Withdrawal amount must be at least NGN 0.01.',
                                    ])
                                    ->helperText('Available balance: ₦'.number_format($availableBalance, 2));
                            } else {
                                $amountField
                                    ->disabled()
                                    ->helperText('This account has no available funds to withdraw.');
                            }

                            return [
                                $amountField,

                                DatePicker::make('date')
                                    ->label('Withdrawal Date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),

                                TextInput::make('reference')
                                    ->label('Reference / Cheque No.')
                                    ->maxLength(255)
                                    ->placeholder('e.g., CHQ-00345 or leave blank for auto')
                                    ->default(fn () => Transaction::generateReference('withdrawal')),

                                Textarea::make('description')
                                    ->label('Reason / Description')
                                    ->placeholder('e.g., Bank charges, Emergency plumbing repair, Stationery')
                                    ->required()
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (BankAccount $record, array $data) {
                            try {
                                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                                    $sourceAccount = BankAccount::query()
                                        ->whereKey($record->id)
                                        ->lockForUpdate()
                                        ->firstOrFail();

                                    $availableBalance = (float) $sourceAccount->ledger_balance - (float) ($sourceAccount->reserved_balance ?? 0);
                                    $requestedAmount = (float) $data['amount'];

                                    if ($availableBalance <= 0 || $requestedAmount > $availableBalance) {
                                        throw \Illuminate\Validation\ValidationException::withMessages([
                                            'amount' => 'Withdrawal amount cannot exceed the available balance of NGN '.number_format(max(0, $availableBalance), 2).'.',
                                        ]);
                                    }

                                    Transaction::create([
                                        'bank_account_id' => $sourceAccount->id,
                                        'type' => 'withdrawal',
                                        'amount' => $requestedAmount,
                                        'date' => $data['date'],
                                        'reference' => $data['reference'],
                                        'description' => $data['description'],
                                        'is_system' => false,
                                    ]);
                                });

                                Notification::make()
                                    ->title('Withdrawal Successful')
                                    ->body('Funds have been withdrawn.')
                                    ->success()
                                    ->send();
                            } catch (\Illuminate\Validation\ValidationException $e) {
                                throw $e;
                            } catch (\App\Exceptions\InsufficientBankBalanceException $e) {
                                Notification::make()
                                    ->title('Insufficient Funds')
                                    ->body('The account does not have enough balance for this withdrawal.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
