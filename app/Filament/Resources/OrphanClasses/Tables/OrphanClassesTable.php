<?php

namespace App\Filament\Resources\OrphanClasses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrphanClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Class Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Displaying the number of orphans assigned to this level
                TextColumn::make('enrollments_count')
                    ->counts('enrollments')
                    ->label('Students')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Date Added')
                    ->dateTime('d M, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
