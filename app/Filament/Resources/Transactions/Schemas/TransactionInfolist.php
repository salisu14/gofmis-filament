<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('bankAccount.account_name')
                                    ->label('Bank Account')
                                    ->icon('heroicon-o-building-library'),

                                TextEntry::make('destinationBankAccount.account_name')
                                    ->label('Destination Bank Account')
                                    ->icon('heroicon-o-building-library')
                                    ->visible(fn($record) => $record->type === 'transfer'),

                                TextEntry::make('reference')
                                    ->label('Reference No.')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable()
                                    ->placeholder('N/A'),

                                TextEntry::make('type')
                                    ->label('Type')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'deposit', 'loan_repayment' => 'success',
                                        'withdrawal', 'loan_disbursement' => 'danger',
                                        'transfer' => 'info',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('date')
                                    ->label('Transaction Date')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-o-calendar-days'),

                                TextEntry::make('amount')
                                    ->label('Total Amount')
                                    ->money('NGN')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('success'),

                                TextEntry::make('description')
                                    ->label('Narration')
                                    ->placeholder('No description provided.')
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Audit Trail')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Transaction ID')
                                    ->limit(8)
                                    ->tooltip(fn($state) => $state)
                                    ->copyable()
                                    ->icon('heroicon-o-finger-print'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ]),
                    ])->collapsible(),
            ]);
    }
}
