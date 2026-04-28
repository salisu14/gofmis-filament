<?php

namespace App\Filament\Resources\ZoneTransfers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ZoneTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deceased.full_name')
                    ->label('Family Head')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fromZone.name')
                    ->label('From Zone')
                    ->badge()
                    ->color('danger'),

                IconColumn::make('arrow')
                    ->label('')
                    ->icon('heroicon-o-arrow-right')
                    ->color('gray'),

                TextColumn::make('toZone.name')
                    ->label('To Zone')
                    ->badge()
                    ->color('success'),

                TextColumn::make('mover.name')
                    ->label('Transferred By'),

                TextColumn::make('reason')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Transfer Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('from_zone_id')
                    ->label('From Zone')
                    ->relationship('fromZone', 'name'),

                SelectFilter::make('to_zone_id')
                    ->label('To Zone')
                    ->relationship('toZone', 'name'),

                Filter::make('created_today')
                    ->label('Today')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),
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
