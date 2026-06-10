<?php

namespace App\Filament\Resources\Transactions\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'transactionLines';
    protected static ?string $title = 'Journal Lines (Debits & Credits)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('description')
                    ->label('Line Description')
                    ->required() // Made required since it's no longer tied to an account name
                    ->maxLength(255)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('debit')
                            ->label('Debit (₦)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('credit', null)),

                        TextInput::make('credit')
                            ->label('Credit (₦)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('debit', null)),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->weight('bold'), // Replaced the Account column
                TextColumn::make('debit')
                    ->label('Debit')
                    ->money('NGN')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('NGN')->label('Total Debit')),
                TextColumn::make('credit')
                    ->label('Credit')
                    ->money('NGN')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('NGN')->label('Total Credit')),
            ])
            ->defaultSort('id', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add Line Item')
                    ->visible(fn () => !$this->ownerRecord->is_system), // ✅ Check the flag
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => !$this->ownerRecord->is_system), // ✅ Check the flag
                DeleteAction::make()
                    ->visible(fn () => !$this->ownerRecord->is_system), // ✅ Check the flag
            ]);
    }
}
