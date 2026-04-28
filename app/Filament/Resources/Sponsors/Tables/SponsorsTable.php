<?php

namespace App\Filament\Resources\Sponsors\Tables;

use App\Enums\SponsorType;
use App\Models\Sponsor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SponsorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Sponsor Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                // Displaying number of sponsorships to give immediate performance context
                TextColumn::make('sponsorships_count')
                    ->counts('sponsorships')
                    ->label('Active Support')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                // Financial summary column similar to the selected OrphanEducation code style
                TextColumn::make('total_committed')
                    ->label('Total Commitment')
                    ->state(fn(Sponsor $record) => $record->sponsorships()->sum('amount_committed'))
                    ->money('NGN')
                    ->color('success')
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('type')
                    ->options(SponsorType::class),
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
