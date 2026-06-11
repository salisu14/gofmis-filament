<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Resources\ProjectExpenses\ProjectExpenseResource;
use App\Services\ProjectService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
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
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('expenses')
                        ->label('Expenses')
                        ->icon('heroicon-m-banknotes')
                        ->url(fn($record) => ProjectExpenseResource::getUrl('index', [
                            'tableFilters[project_id][value]' => $record->id,
                        ])),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->visible(fn($record) => $record->status === ProjectStatus::PLANNING)
                        ->requiresConfirmation()
                        ->action(function (ProjectService $service) {
                            $service->approveProject($this->record);

                            Notification::make()
                                ->title('Project approved')
                                ->body('Default milestones have been created.')
                                ->success()
                                ->send();

                            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                        }),

                    Action::make('start')
                        ->label('Start Work')
                        ->icon('heroicon-m-play')
                        ->color('warning')
                        ->visible(fn($record) => $record->status === ProjectStatus::APPROVED)
                        ->requiresConfirmation()
                        ->action(function (ProjectService $service) {
                            $service->startProject($this->record);

                            Notification::make()->title('Project started')->success()->send();

                            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                        }),

                    Action::make('complete')
                        ->label('Mark Complete')
                        ->icon('heroicon-m-flag')
                        ->color('success')
                        ->visible(fn($record) => $record->status === ProjectStatus::IN_PROGRESS)
                        ->requiresConfirmation()
                        ->action(function (ProjectService $service) {
                            $service->completeProject($this->record);

                            Notification::make()->title('Project completed')->success()->send();

                            // ✅ FIXED
                            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                        }),

                    Action::make('hold')
                        ->label('Place on Hold')
                        ->icon('heroicon-m-pause')
                        ->color('danger')
                        ->visible(fn($record) => $record->status === ProjectStatus::IN_PROGRESS)
                        ->schema([
                            \Filament\Forms\Components\Textarea::make('reason')
                                ->required()
                                ->label('Reason for hold'),
                        ])
                        ->action(function (array $data, ProjectService $service) {
                            $service->holdProject($this->record, $data['reason']);

                            Notification::make()->title('Project placed on hold')->warning()->send();

                            // ✅ FIXED
                            $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                        }),
                ])
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
