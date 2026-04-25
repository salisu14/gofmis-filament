<?php

namespace App\Filament\Resources\Zones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Zone Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('town.name')
                    ->label('Town')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('town.city.name')
                    ->label('City')
                    ->searchable()
                    ->color('gray'),

                TextColumn::make('town.city.state.name')
                    ->label('State')
                    ->searchable()
                    ->badge(),

                TextColumn::make('address')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->relationship('town.city.state', 'name')
                    ->label('Filter by State')
                    ->searchable(),

                SelectFilter::make('town')
                    ->relationship('town', 'name')
                    ->label('Filter by Town')
                    ->searchable(),
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
