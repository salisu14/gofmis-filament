<?php

namespace App\Filament\Resources\Prescriptions\Tables;

use App\Enums\PrescriptionStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PrescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prescription_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('prescribable.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Prescription $record) => str_replace('App\Models\\', '', $record->prescribable_type)),

                TextColumn::make('illnessModel.name')
                    ->label('Diagnosis')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Treatment Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('treated_at')
                    ->label('Completed At')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('medications.name')
                    ->label('Meds')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->state(fn (Prescription $record) => $record->total_cost)
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('doctor_name')
                    ->label('Doctor')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Issued By')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Treatment Status')
                    ->options(PrescriptionStatus::class),

                SelectFilter::make('prescribable_type')
                    ->label('Patient Type')
                    ->options([
                        'App\Models\Orphan' => 'Orphan',
                        'App\Models\Widow' => 'Widow',
                    ]),

                Filter::make('prescription_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('prescription_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('prescription_date', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Prescription $record) => $record->isPending()),
                \App\Filament\Actions\MarkHealthcareTreatedAction::make(),
                DeleteAction::make()
                    ->visible(fn (Prescription $record) => $record->isPending()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deletable = $records->reject(fn (Prescription $record) => $record->isTreated());
                            $protectedCount = $records->count() - $deletable->count();

                            $deletable->each(fn (Prescription $record) => $record->delete());

                            if ($protectedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Protected Clinical Records Skipped')
                                    ->body("{$protectedCount} completed healthcare request(s) were protected from deletion.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
