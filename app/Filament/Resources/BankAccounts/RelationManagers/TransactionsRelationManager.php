<?php

namespace App\Filament\Resources\BankAccounts\RelationManagers;

use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Models\BankAccount;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transaction History';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'deposit' => 'Deposit / Credit',
                        'withdrawal' => 'Withdrawal / Debit',
                        'transfer' => 'Internal Transfer',
                    ])
                    ->required()
                    ->live()
                    ->native(false)
                    ->disabledOn('edit'),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('₦')
                    ->required()
                    ->minValue(1)
                    ->disabledOn('edit')
                    ->helperText(function (Get $get, RelationManager $livewire) {
                        if ($get('type') !== 'withdrawal' && $get('type') !== 'transfer') {
                            return null;
                        }
                        $account = $livewire->getOwnerRecord();
                        $available = (float) $account->ledger_balance - (float) ($account->reserved_balance ?? 0);
                        return "Available balance: ₦" . number_format($available, 2);
                    }),

                Select::make('destination_bank_account_id')
                    ->label('Destination Account')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => $get('type') === 'transfer')
                    ->required(fn (Get $get) => $get('type') === 'transfer')
                    ->options(fn (RelationManager $livewire) => BankAccount::whereKeyNot($livewire->getOwnerRecord()->id)->pluck('account_name', 'id'))
                    ->disabledOn('edit'),

                DatePicker::make('date')
                    ->label('Transaction Date')
                    ->default(now())
                    ->required()
                    ->native(false)
                    ->disabledOn('edit'),

                TextInput::make('description')
                    ->label('Memo / Description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->disabledOn('edit'),

                Hidden::make('bank_account_id')
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->id),

                Hidden::make('is_system')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reference')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Reference copied!')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deposit', 'loan_repayment', 'imprest_replenishment_reversal', 'imprest_expense_void' => 'success',
                        'withdrawal', 'loan_disbursement', 'imprest_expense' => 'danger',
                        'transfer' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),

                TextColumn::make('description')
                    ->label('Memo')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->description),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($record) {
                        $prefix = $record->isCreditType() ? '+' : '-';
                        $amount = number_format((float) $record->amount, 2);
                        return "{$prefix} ₦{$amount}";
                    })
                    ->color(fn ($record) => $record->isCreditType() ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('destinationBankAccount.account_name')
                    ->label('Destination')
                    ->default('—')
                    ->visible(fn ($record) => $record?->type === 'transfer' || $record?->destination_bank_account_id !== null),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer' => 'Transfer',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Disbursement',
                        'imprest_funding' => 'Imprest Funding',
                        'imprest_expense' => 'Imprest Expense',
                    ]),
            ])
            ->headerActions([
                // Only allow manual creation on parent accounts (per model validation rules)
//                CreateAction::make()
//                    ->modalWidth('2xl')
//                    ->visible(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->canPerformManualBankMovement()),
            ])
            ->recordActions([
//                ViewAction::make(),
            ]);
    }
}
