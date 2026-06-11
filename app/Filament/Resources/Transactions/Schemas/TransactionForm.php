<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSystem = fn (?Model $record): bool => $record?->is_system ?? false;

        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('bank_account_id')
                                    ->label('Bank Account')
                                    ->relationship('bankAccount', 'account_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    // 🔒 LOCKED: Bank account can never be changed after creation
                                    ->disabled(fn (string $operation, ?Model $record): bool => $operation === 'edit' || $isSystem($record))
                                    ->dehydrated(),

                                Select::make('type')
                                    ->label('Transaction Type')
                                    ->options([
                                        'deposit' => 'Deposit',
                                        'withdrawal' => 'Withdrawal',
                                        'intervention' => 'Intervention',
                                        'imprest' => 'Imprest',
                                        'loan_disbursement' => 'Loan Disbursement',
                                        'loan_repayment' => 'Loan Repayment',
                                    ])
                                    ->required()
                                    // 🔒 LOCKED: Type can never be changed after creation
                                    ->disabled(fn (string $operation, ?Model $record): bool => $operation === 'edit' || $isSystem($record))
                                    ->dehydrated(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('reference')
                                    ->label('Reference No.')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., JRN-2023-0001')
//                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->disabled($isSystem)
                                    ->columnSpan(1),

                                DatePicker::make('date')
                                    ->label('Transaction Date')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->maxDate(now())
                                    // 🔒 Lock on edit
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(),
                            ]),
                    ]),

                Section::make('Amount & Description')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Total Amount')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->step(0.01)
                            // 🔒 LOCKED: Amount changes must be entered as a new transaction or adjustment.
                            ->disabled(fn (string $operation, ?Model $record): bool =>
                                $isSystem($record)
                                || $operation === 'edit'
                                || $record?->transactionable_type !== null
                                || $record?->transactionLines()->exists()
                            )
                            ->dehydrated(),

                        Textarea::make('description')
                            ->label('Narration / Description')
                            ->columnSpanFull()
                            ->maxLength(1000)
                            ->placeholder('Provide a clear description for this transaction...')
                            ->disabled($isSystem),
                    ]),
            ]);
    }
}
