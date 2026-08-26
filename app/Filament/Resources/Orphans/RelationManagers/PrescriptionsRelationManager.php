<?php

namespace App\Filament\Resources\Orphans\RelationManagers;

use App\Filament\Resources\Prescriptions\PrescriptionResource;
use App\Filament\Resources\Prescriptions\Schemas\PrescriptionForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';

    protected static ?string $relatedResource = PrescriptionResource::class;

    protected static ?string $recordTitleAttribute = 'illness';

    protected static ?string $title = 'Medical History & Prescriptions';

    public function form(Schema $schema): Schema
    {
        return PrescriptionForm::configure($schema, includePatient: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->groups([])
            ->columns([
                TextColumn::make('prescription_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('illnessModel.name')
                    ->label('Diagnosis')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('doctor_name')
                    ->label('Doctor')
                    ->searchable(),

                TextColumn::make('medications.name')
                    ->label('Meds')
                    ->badge()
                    ->separator(','),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->state(fn ($record) => (float) $record->lab_test_cost + (float) $record->drug_cost)
                    ->color('success'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Prescription')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl')
                    ->using(function (array $data, RelationManager $livewire): \App\Models\Prescription {
                        $orphan = $livewire->getOwnerRecord();
                        $medications = $data['medications'] ?? [];
                        unset($data['medications']);

                        $data['user_id'] = $data['user_id'] ?? auth()->id();
                        $data['prescription_date'] = $data['prescription_date'] ?? now()->toDateString();
                        $data['lab_test_cost'] = $data['lab_test_cost'] ?? 0;
                        $data['drug_cost'] = $data['drug_cost'] ?? 0;

                        if (\Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'illness')) {
                            $illness = \App\Models\Illness::find($data['illness_id'] ?? null);
                            $data['illness'] = $illness?->name ?? 'Unspecified diagnosis';
                        }

                        $prescription = new \App\Models\Prescription($data);
                        $prescription->prescribable()->associate($orphan);
                        $prescription->save();

                        if (! empty($medications)) {
                            $prescription->medications()->sync($medications);
                        }

                        return $prescription;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ]);
    }
}
