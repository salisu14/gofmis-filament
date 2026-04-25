<?php

namespace App\Filament\Resources\InterventionRequests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InterventionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('orphan.full_name')
                    ->label('Orphan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type.name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fulfilled' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('verification_status')
                    ->label('Verified')
                    ->badge()
                    ->color(fn ($state) => $state === 'verified' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status'),
                SelectFilter::make('intervention_type_id')
                    ->relationship('type', 'name')
                    ->label('Type'),
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
