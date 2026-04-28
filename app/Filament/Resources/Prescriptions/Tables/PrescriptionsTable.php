<?php

namespace App\Filament\Resources\Prescriptions\Tables;

use App\Models\Prescription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->description(fn(Prescription $record) => str_replace('App\Models\\', '', $record->prescribable_type)),

                TextColumn::make('illness')
                    ->label('Diagnosis')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('medications.name')
                    ->label('Meds')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->state(fn(Prescription $record) => $record->total_cost)
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
                            ->when($data['from'], fn($q) => $q->whereDate('prescription_date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('prescription_date', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
