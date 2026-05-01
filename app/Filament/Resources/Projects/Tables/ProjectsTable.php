<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state->label())
                    ->icon(fn($state) => $state->icon()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state->color()),

                TextColumn::make('zone.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('budget_allocated')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('budget_spent')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->badge()
                    ->color(fn($state) => $state >= 100 ? 'success' : 'warning'),

                TextColumn::make('expected_completion_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('coordinator.name')
                    ->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(ProjectType::cases())->mapWithKeys(
                        fn($type) => [$type->value => $type->label()]
                    )),
                SelectFilter::make('status')
                    ->options(collect(ProjectStatus::cases())->mapWithKeys(
                        fn($status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('expenses')
                    ->label('Expenses')
                    ->icon('heroicon-m-banknotes')
                    ->url(fn($record) => ProjectExpenseResource::getUrl('index', [
                        'tableFilters[project_id][value]' => $record->id,
                    ])),
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
