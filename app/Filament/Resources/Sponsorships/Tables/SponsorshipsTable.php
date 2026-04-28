<?php

namespace App\Filament\Resources\Sponsorships\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SponsorshipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orphan.full_name')
                    ->label('Orphan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('sponsor.name')
                    ->label('Sponsor')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->sponsor?->type?->getLabel()),

                TextColumn::make('amount_committed')
                    ->label('Committed')
                    ->money('NGN')
                    ->sortable()
                    ->summarize(Sum::make()->money('NGN')->label('Total Commitments')),

                TextColumn::make('start_date')
                    ->label('Start')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End')
                    ->date()
                    ->sortable()
                    ->placeholder('Ongoing'),

                TextColumn::make('deleted_at')
                    ->label('Archived Status')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn($state) => $state ? 'Archived' : 'Active'),
            ])
            ->filters([
                TrashedFilter::make(),
                Filter::make('active_sponsorships')
                    ->label('Active only')
                    ->query(fn($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now())),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
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
