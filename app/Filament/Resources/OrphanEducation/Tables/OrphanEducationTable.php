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
                \Filament\Tables\Filters\TrashedFilter::make(),
                \Filament\Tables\Filters\TernaryFilter::make('is_current')
                    ->label('Active Students Only')
                    ->indicator('Current Enrollments'),
                \Filament\Tables\Filters\SelectFilter::make('institution_id')
                    ->label('School')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload(),
                \Filament\Tables\Filters\SelectFilter::make('orphan_class_id')
                    ->label('Class/Grade')
                    ->relationship('orphanClass', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('promote')
                    ->label('Promote/Demote')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('new_class_id')
                            ->label('New Class/Grade')
                            ->relationship('orphanClass', 'name')
                            ->required()
                            ->searchable(),
                        \Filament\Forms\Components\DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (OrphanEducation $record, array $data): void {
                        $record->update([
                            'is_current' => false,
                            'ended_at' => \Carbon\Carbon::parse($data['effective_date'])->subDay(),
                        ]);

                        $newRecord = $record->replicate([
                            'id',
                            'reference',
                            'is_current',
                            'started_at',
                            'ended_at',
                            'created_at',
                            'updated_at',
                        ]);
                        $newRecord->orphan_class_id = $data['new_class_id'];
                        $newRecord->started_at = $data['effective_date'];
                        $newRecord->is_current = true;
                        $newRecord->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Education Record Updated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_promote')
                        ->label('Bulk Promote/Demote')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->form([
                            \Filament\Forms\Components\Select::make('new_class_id')
                                ->label('New Class/Grade')
                                ->options(\App\Models\OrphanClass::pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            \Filament\Forms\Components\DatePicker::make('effective_date')
                                ->label('Effective Date')
                                ->default(now())
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_current' => false,
                                    'ended_at' => \Carbon\Carbon::parse($data['effective_date'])->subDay(),
                                ]);

                                $newRecord = $record->replicate([
                                    'id',
                                    'reference',
                                    'is_current',
                                    'started_at',
                                    'ended_at',
                                    'created_at',
                                    'updated_at',
                                ]);
                                $newRecord->orphan_class_id = $data['new_class_id'];
                                $newRecord->started_at = $data['effective_date'];
                                $newRecord->is_current = true;
                                $newRecord->save();
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Education Records Updated')
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
