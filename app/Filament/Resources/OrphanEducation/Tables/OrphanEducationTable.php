<?php

namespace App\Filament\Resources\OrphanEducation\Tables;

use App\Enums\AcademicProgressionDecision;
use App\Models\Institution;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Services\WesternEducationProgressionService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                TextColumn::make('reference')
                    ->label('Education Ref')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->searchable()
                    ->sortable()
                    ->description(fn (OrphanEducation $record) => "Level: {$record->level}"),

                TextColumn::make('academic_session')
                    ->label('Session')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('progression_decision')
                    ->label('Outcome')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof AcademicProgressionDecision ? $state->label() : ($state ? ucfirst($state) : 'N/A'))
                    ->color(fn ($state) => $state instanceof AcademicProgressionDecision ? $state->color() : 'gray')
                    ->sortable(),

                TextColumn::make('total_paid')
                    ->label('Paid')
                    ->state(fn (OrphanEducation $record) => $record->total_paid)
                    ->money('NGN')
                    ->color('success')
                    ->alignEnd(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn (OrphanEducation $record) => $record->balance)
                    ->money('NGN')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignEnd(),

                IconColumn::make('is_current')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                TernaryFilter::make('is_current')
                    ->label('Enrollment Status')
                    ->trueLabel('Active Enrollments Only')
                    ->falseLabel('Past / Graduated Only')
                    ->indicator('Enrollment Status'),

                SelectFilter::make('institution_id')
                    ->label('School')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('orphan_class_id')
                    ->label('Class/Level')
                    ->relationship('orphanClass', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('academic_session')
                    ->label('Academic Session')
                    ->options(fn () => OrphanEducation::whereNotNull('academic_session')
                        ->distinct()
                        ->pluck('academic_session', 'academic_session')
                        ->toArray())
                    ->searchable(),

                SelectFilter::make('institution_type')
                    ->label('Education Type')
                    ->options([
                        'western' => 'Western Education',
                        'islamiyya' => 'Islamiyya',
                        'vocational' => 'Vocational',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('institution', fn ($q) => $q->where('type', $data['value']));
                        }
                    }),

                SelectFilter::make('progression_decision')
                    ->label('Progression Outcome')
                    ->options([
                        'promoted' => 'Promoted',
                        'repeated' => 'Repeated / Retained',
                        'demoted' => 'Demoted',
                        'graduated' => 'Graduated / Completed',
                        'transferred' => 'Transferred School',
                    ]),

                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->options(fn () => \App\Models\Zone::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('orphan.deceased', fn ($q) => $q->where('zone_id', $data['value']));
                        }
                    })
                    ->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'super_admin'])),
            ])
            ->recordActions([
                Action::make('academic_progression')
                    ->label('Academic Progression')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->visible(fn (OrphanEducation $record) => app(WesternEducationProgressionService::class)->canProgress($record))
                    ->form([
                        Select::make('decision')
                            ->label('Progression Decision')
                            ->options([
                                'promoted' => 'Promote (Next Class)',
                                'repeated' => 'Repeat / Retain (Same Class)',
                                'demoted' => 'Demote (Lower Class)',
                                'graduated' => 'Graduate / Complete (Finalize)',
                                'transferred' => 'Transfer School',
                                'withdrawn' => 'Withdraw (Administrative Exit)',
                                'dropped_out' => 'Drop Out (Discontinue)',
                            ])
                            ->default('promoted')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, OrphanEducation $record) {
                                $service = app(WesternEducationProgressionService::class);
                                if ($state === 'promoted') {
                                    $nextClass = $service->getNextLogicalClass($record->orphanClass);
                                    if ($nextClass) {
                                        $set('new_class_id', $nextClass->id);
                                    }
                                } elseif ($state === 'demoted') {
                                    $prevClass = $service->getPreviousLogicalClass($record->orphanClass);
                                    if ($prevClass) {
                                        $set('new_class_id', $prevClass->id);
                                    }
                                } elseif ($state === 'repeated') {
                                    $set('new_class_id', $record->orphan_class_id);
                                }
                            }),

                        TextInput::make('academic_session')
                            ->label('Target Academic Session')
                            ->placeholder('e.g. 2025/2026')
                            ->default(fn (OrphanEducation $record) => app(WesternEducationProgressionService::class)->getNextSequentialSession($record->academic_session, $record->started_at))
                            ->required(fn ($get) => ! in_array($get('decision'), ['graduated', 'withdrawn', 'dropped_out'], true))
                            ->hidden(fn ($get) => in_array($get('decision'), ['graduated', 'withdrawn', 'dropped_out'], true)),

                        Select::make('new_class_id')
                            ->label('Target Class / Grade')
                            ->options(fn () => OrphanClass::pluck('name', 'id'))
                            ->default(fn (OrphanEducation $record) => app(WesternEducationProgressionService::class)->getNextLogicalClass($record->orphanClass)?->id ?? $record->orphan_class_id)
                            ->searchable()
                            ->disabled(fn ($get) => in_array($get('decision'), ['promoted', 'repeated', 'demoted'], true))
                            ->dehydrated()
                            ->required(fn ($get) => in_array($get('decision'), ['promoted', 'repeated', 'demoted', 'transferred'], true))
                            ->hidden(fn ($get) => in_array($get('decision'), ['graduated', 'withdrawn', 'dropped_out'], true)),

                        Select::make('new_institution_id')
                            ->label('Target Institution')
                            ->options(fn () => Institution::pluck('name', 'id'))
                            ->default(fn (OrphanEducation $record) => $record->institution_id)
                            ->searchable()
                            ->required(fn ($get) => $get('decision') === 'transferred')
                            ->visible(fn ($get) => $get('decision') === 'transferred'),

                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required(),

                        Textarea::make('reason')
                            ->label('Progression Justification / Reason')
                            ->placeholder('Enter justification or notes for this academic decision...')
                            ->required(fn ($get) => in_array($get('decision'), ['demoted', 'transferred', 'graduated', 'withdrawn', 'dropped_out'], true)),
                    ])
                    ->action(function (OrphanEducation $record, array $data): void {
                        try {
                            $service = app(WesternEducationProgressionService::class);
                            $service->progress($record, $data['decision'], $data, auth()->user());

                            Notification::make()
                                ->title('Academic Progression Recorded')
                                ->body('The student academic progression decision has been recorded successfully.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Progression Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_academic_progression')
                        ->label('Bulk Academic Progression')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->form([
                            Select::make('decision')
                                ->label('Progression Decision')
                                ->options([
                                    'promoted' => 'Promote (Next Sequential Class)',
                                    'repeated' => 'Repeat / Retain (Same Class)',
                                    'graduated' => 'Graduate / Complete',
                                ])
                                ->default('promoted')
                                ->required(),

                            TextInput::make('academic_session')
                                ->label('Target Academic Session')
                                ->placeholder('e.g. 2025/2026')
                                ->default(fn () => now()->year.'/'.(now()->year + 1))
                                ->required(fn ($get) => $get('decision') !== 'graduated'),

                            DatePicker::make('effective_date')
                                ->label('Effective Date')
                                ->default(now())
                                ->required(),

                            Textarea::make('reason')
                                ->label('Progression Reason / Notes')
                                ->nullable(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            $service = app(WesternEducationProgressionService::class);
                            $processedCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $record) {
                                if (! $service->canProgress($record)) {
                                    $skippedCount++;

                                    continue;
                                }

                                try {
                                    $service->progress($record, $data['decision'], $data, auth()->user());
                                    $processedCount++;
                                } catch (\Exception $e) {
                                    $skippedCount++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Progression Processed')
                                ->body("Successfully processed {$processedCount} student(s). Skipped {$skippedCount} non-Western or inactive record(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
