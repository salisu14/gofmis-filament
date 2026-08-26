<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use App\Models\Project;
use App\Services\ProjectService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->icon(fn ($state) => $state->icon()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state->color()),

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
                    ->color(fn ($state) => $state >= 100 ? 'success' : 'warning'),

                TextColumn::make('expected_completion_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('coordinator.name')
                    ->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(ProjectType::cases())->mapWithKeys(
                        fn ($type) => [$type->value => $type->label()]
                    )),
                SelectFilter::make('status')
                    ->options(collect(ProjectStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('expenses')
                        ->label('Expenses')
                        ->icon('heroicon-m-banknotes')
                        ->url(fn (Project $record) => ProjectExpenseResource::getUrl('index', [
                            'tableFilters[project_id][value]' => $record->id,
                        ])),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::PLANNING)
                        ->requiresConfirmation()
                        ->action(function (Project $record, ProjectService $service): void {
                            $service->approveProject($record);

                            Notification::make()
                                ->title('Project approved')
                                ->body('Default milestones have been created.')
                                ->success()
                                ->send();
                        }),

                    Action::make('start')
                        ->label('Start Work')
                        ->icon('heroicon-m-play')
                        ->color('warning')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::APPROVED)
                        ->requiresConfirmation()
                        ->action(function (Project $record, ProjectService $service): void {
                            $service->startProject($record);

                            Notification::make()->title('Project started')->success()->send();
                        }),

                    Action::make('complete')
                        ->label('Mark Complete')
                        ->icon('heroicon-m-flag')
                        ->color('success')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::IN_PROGRESS)
                        ->requiresConfirmation()
                        ->action(function (Project $record, ProjectService $service): void {
                            $service->completeProject($record);

                            Notification::make()->title('Project completed')->success()->send();
                        }),

                    Action::make('hold')
                        ->label('Place on Hold')
                        ->icon('heroicon-m-pause')
                        ->color('danger')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::IN_PROGRESS)
                        ->schema([
                            Textarea::make('reason')
                                ->required()
                                ->label('Reason for hold'),
                        ])
                        ->action(function (Project $record, array $data, ProjectService $service): void {
                            $service->holdProject($record, $data['reason']);

                            Notification::make()->title('Project placed on hold')->warning()->send();
                        }),

                    Action::make('resume')
                        ->label('Resume Project')
                        ->icon('heroicon-m-play-pause')
                        ->color('info')
                        ->visible(fn (Project $record): bool => $record->status === ProjectStatus::ON_HOLD)
                        ->requiresConfirmation()
                        ->action(function (Project $record, ProjectService $service): void {
                            $service->resumeProject($record);

                            Notification::make()->title('Project resumed')->success()->send();
                        }),
                ]),
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
