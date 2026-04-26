<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers;

use App\Filament\Imprest\Resources\ImprestTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title = 'Transaction History';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            // Read-only view in relation manager
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('voucher_no')
            ->columns([
                Tables\Columns\TextColumn::make('voucher_no')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'voided' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Deceased')
                    ->searchable(),

                Tables\Columns\TextColumn::make('item_service')
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('USD')
                    ->alignment('right'),

                Tables\Columns\IconColumn::make('receipt_attached')
                    ->boolean()
                    ->label('Receipt'),

                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'voided' => 'Voided',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => ImprestTransactionResource::getUrl('create', ['fund_id' => $this->getOwnerRecord()->id])),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ImprestTransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('date', 'desc');
    }
}
