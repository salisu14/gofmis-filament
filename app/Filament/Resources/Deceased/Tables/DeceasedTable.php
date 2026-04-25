<?php

namespace App\Filament\Resources\Deceased\Tables;

use App\Enums\VulnerabilityStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DeceasedTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->description(fn ($record) => "Reg: {$record->reg_no}"),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('vulnerability_status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->zone?->town?->name . ', ' . $record->zone?->town?->city?->name),

                TextColumn::make('orphans_count')
                    ->counts('orphans')
                    ->label('Orphans')
                    ->badge()
                    ->color('info'),

                TextColumn::make('widows_count')
                    ->counts('widows')
                    ->label('Widows')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('date_registered')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vulnerability_status')
                    ->options(VulnerabilityStatus::class),
                TernaryFilter::make('has_death_cert')
                    ->label('Death Certificate'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
