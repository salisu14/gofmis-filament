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
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
