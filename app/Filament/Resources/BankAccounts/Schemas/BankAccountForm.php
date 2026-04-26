<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Identity')
                    ->description('Primary details and ownership of the bank account.')
                    ->icon('heroicon-m-building-library')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('account_name')
                                ->label('Account Holder Name')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. Grace Orphans General Fund'),

                            TextInput::make('account_number')
                                ->label('Account Number')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50)
                                ->placeholder('e.g. 0123456789'),
                        ]),

                        Select::make('user_id')
                            ->label('Primary Signatory / Manager')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->hint('The staff member responsible for this account.'),
                    ]),

                Section::make('Financial Status')
                    ->description('Real-time balance tracking and reservation management.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('opening_balance')
                                ->label('Initial Deposit')
                                ->numeric()
                                ->prefix('₦')
                                ->default(0)
                                ->required()
                                ->disabledOn('edit')
                                ->helperText('The starting balance when registered.'),

                            TextInput::make('ledger_balance')
                                ->label('Current Ledger Balance')
                                ->numeric()
                                ->prefix('₦')
                                ->helperText('Actual cash physicaly in the bank.')
                                ->disabled(), // Should be managed via transactions

                            TextInput::make('reserved_balance')
                                ->label('Reserved Funds')
                                ->numeric()
                                ->prefix('₦')
                                ->helperText('Funds tied up in pending approvals.')
                                ->disabled(),
                        ]),

                        Placeholder::make('available_balance')
                            ->label('Available for Disbursement')
                            ->content(fn ($record) => $record
                                ? '₦ ' . number_format($record->ledger_balance - $record->reserved_balance, 2)
                                : '₦ 0.00'
                            )
                            ->extraAttributes(['class' => 'text-2xl font-bold text-primary-600']),
                    ]),
            ]);
    }
}
