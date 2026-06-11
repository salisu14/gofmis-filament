<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Models\BankAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
