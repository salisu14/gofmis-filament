<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Exceptions\InsufficientBankBalanceException;
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

                TextColumn::make('ledger_balance')
                    ->label('Own Balance')
                    ->money('NGN')
                    ->alignEnd(),

                TextColumn::make('consolidated_balance')
                    ->label('Consolidated Balance')
                    ->money('NGN')
                    ->alignEnd()
                    ->weight('bold')
                    ->visible(fn($record) => $record?->isMainAccount())
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
                    ->state(fn(BankAccount $record) => $record->ledger_balance - $record->reserved_balance)
                    ->money('NGN')
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
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
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),

                    Action::make('recordDeposit')
                        ->label('Record Deposit')
                        ->icon('heroicon-m-arrow-down-circle')
                        ->color('success')
                        ->modalHeading('Record External Deposit')
                        ->schema([
                            TextInput::make('amount')->required()->numeric()->prefix('₦'),
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
                                'reference' => 'DEP-' . strtoupper(substr(md5(now()->timestamp), 0, 8)),
                                'is_system' => false,
                            ]);
                        }),

                    // ✅ NEW: Dedicated Transfer Action
                    Action::make('transferFunds')
                        ->label('Transfer Funds')
                        ->icon('heroicon-m-arrow-right-circle')
                        ->color('info')
                        ->modalHeading('Transfer Funds Between Accounts')
                        ->modalDescription(fn(BankAccount $record) => "Source Account: {$record->account_name} (Balance: ₦" . number_format($record->ledger_balance, 2) . ")")
                        ->requiresConfirmation()
                        ->schema(fn(BankAccount $record) => [ // ✅ Inject the $record into the form
                            Select::make('destination_bank_account_id')
                                ->label('Destination Account')
                                // ✅ FIX: Standard Eloquent query excluding the current record
                                ->options(BankAccount::where('id', '!=', $record->id)->pluck('account_name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('amount')
                                ->label('Transfer Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01),

                            DatePicker::make('date')
                                ->label('Transfer Date')
                                ->default(now())
                                ->required()
                                ->native(false),

                            TextInput::make('reference')
                                ->label('Reference')
                                ->default('TRF-' . strtoupper(substr(md5(now()->timestamp), 0, 8)))
                                ->required()
                                ->maxLength(255),

                            Textarea::make('description')
                                ->label('Reason for Transfer')
                                ->placeholder('e.g., Moving funds to repayment bucket')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function (BankAccount $record, array $data) {
                            try {
                                // Create the Transaction.
                                // The Transaction model's booted() method will automatically
                                // call postToBank() which handles debiting the source and
                                // crediting the destination!
                                Transaction::create([
                                    'bank_account_id' => $record->id,
                                    'destination_bank_account_id' => $data['destination_bank_account_id'],
                                    'type' => 'transfer',
                                    'amount' => $data['amount'],
                                    'date' => $data['date'],
                                    'reference' => $data['reference'],
                                    'description' => $data['description'],
                                    'is_system' => false, // Manual transfer
                                ]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Transfer Successful')
                                    ->body('Funds have been moved between accounts.')
                                    ->success()
                                    ->send();

                            } catch (\App\Exceptions\InsufficientBankBalanceException $e) {
                                \Filament\Notifications\Notification::make()
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
                        ->modalHeading(fn(BankAccount $record) => "Record Withdrawal from {$record->account_name} (Balance: ₦" . number_format($record->ledger_balance, 2) . ")")
                        ->requiresConfirmation()
                        ->schema([
                            TextInput::make('amount')
                                ->label('Withdrawal Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01),

                            DatePicker::make('date')
                                ->label('Withdrawal Date')
                                ->default(now())
                                ->required()
                                ->native(false),

                            TextInput::make('reference')
                                ->label('Reference / Cheque No.')
                                ->maxLength(255)
                                ->placeholder('e.g., CHQ-00345 or leave blank for auto')
                                ->default('WD-' . strtoupper(substr(md5(now()->timestamp), 0, 8))),

                            Textarea::make('description')
                                ->label('Reason / Description')
                                ->placeholder('e.g., Bank charges, Emergency plumbing repair, Stationery')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function (BankAccount $record, array $data) {
                            try {
                                Transaction::create([
                                    'bank_account_id' => $record->id,
                                    'type' => 'withdrawal',
                                    'amount' => $data['amount'],
                                    'date' => $data['date'],
                                    'reference' => $data['reference'],
                                    'description' => $data['description'],
                                    'is_system' => false,
                                ]);

                                Notification::make()
                                    ->title('Withdrawal Recorded')
                                    ->body('₦' . number_format($data['amount'], 2) . ' has been deducted from the account.')
                                    ->success()
                                    ->send();

                            } catch (InsufficientBankBalanceException $e) {
                                Notification::make()
                                    ->title('Insufficient Funds')
                                    ->body('This account does not have enough balance to cover this withdrawal.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
