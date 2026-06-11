<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deposit', 'loan_repayment' => 'success',
                        'withdrawal', 'loan_disbursement' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('description')
                    ->limit(30)
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'account_name')
                    ->searchable(),
                SelectFilter::make('type')->label('Type'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('date', 'desc');
    }
}
