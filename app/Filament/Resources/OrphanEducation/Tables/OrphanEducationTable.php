<?php

namespace App\Filament\Resources\OrphanEducation\Tables;

use App\Models\OrphanEducation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrphanEducationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orphan.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->searchable()
                    ->sortable()
                    ->description(fn(OrphanEducation $record) => "Level: {$record->orphanClass->name}"),

                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->state(fn(OrphanEducation $record) => $record->total_paid)
                    ->money('NGN')
                    ->color('success')
                    ->alignEnd(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn(OrphanEducation $record) => $record->balance)
                    ->money('NGN')
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignEnd(),

                IconColumn::make('is_current')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('is_fee_supported')
                    ->label('Sponsored')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_current')
                    ->label('Active Students Only')
                    ->indicator('Current Enrollments'),
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
