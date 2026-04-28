<?php

namespace App\Filament\Resources\Medications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class MedicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Medication Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                // Shows how many times this specific drug has been prescribed
                TextColumn::make('prescriptions_count')
                    ->counts('prescriptions')
                    ->label('Times Prescribed')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Added By')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('id')
                    ->label('UUID')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_usage')
                    ->label('Used in Prescriptions')
                    ->query(fn($query) => $query->has('prescriptions')),
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
