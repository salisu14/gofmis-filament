<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers;

use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReconciliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reconciliations';
    protected static ?string $title = 'Reconciliation History';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reconciliation_date')
            ->columns([
                Tables\Columns\TextColumn::make('reconciliation_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cash_on_hand')
                    ->money('NGN')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('receipts_total')
                    ->money('NGN')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('actual_variance')
                    ->money('NGN')
                    ->alignment('right')
                    ->color(fn ($record) => $record->isBalanced() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'flagged' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('auditor.name')
                    ->label('Auditor'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ImprestReconciliationResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('reconciliation_date', 'desc');
    }
}
