<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Enums\IllnessCategory;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clinical Information')
                    ->description('Diagnosis details and attending physician.')
                    ->icon('heroicon-m-beaker')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('doctor_name')
                                ->label('Doctor Name')
                                ->placeholder('e.g. Dr. Adamu Musa')
                                ->required()
                                ->maxLength(255),

                            // Wider illness select with category display and inline creation
                            Select::make('illness_id')
                                ->label('Illness / Diagnosis')
                                ->relationship('illnessModel', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->optionsLimit(50)
                                ->getOptionLabelFromRecordUsing(fn(Illness $record): string => "{$record->name} — {$record->category?->label()}")
                                ->createOptionForm([
                                    Section::make('New Illness')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Illness Name')
                                                ->required()
                                                ->unique(Illness::class, 'name')
                                                ->maxLength(255)
                                                ->placeholder('e.g. Sickle Cell Anemia'),

                                            Select::make('category')
                                                ->label('Category')
                                                ->options(IllnessCategory::class)
                                                ->enum(IllnessCategory::class)
                                                ->required()
                                                ->native(false),

                                            Textarea::make('description')
                                                ->label('Description / Symptoms')
                                                ->rows(2)
                                                ->placeholder('Brief description or common symptoms...'),
                                        ]),
                                ])
                                ->createOptionUsing(function (array $data): string {
                                    return Illness::create($data)->getKey();
                                })
                                ->editOptionForm([
                                    Section::make('Edit Illness')
                                        ->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),

                                            Select::make('category')
                                                ->options(IllnessCategory::class)
                                                ->enum(IllnessCategory::class)
                                                ->required()
                                                ->native(false),

                                            Textarea::make('description')
                                                ->rows(2),
                                        ]),
                                ])
                                ->columnSpan(2) // Makes illness select wider (2/3 of row)
                                ->required(),

                            DatePicker::make('prescription_date')
                                ->label('Date of Visit')
                                ->default(now())
                                ->required()
                                ->native(false)
                                ->closeOnDateSelection(),
                        ]),
                    ]),

                Section::make('Patient & Provider')
                    ->description('Identify the beneficiary and issuing staff member.')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('prescribable_type')
                                ->label('Patient Category')
                                ->options([
                                    Orphan::class => 'Orphan',
                                    Widow::class => 'Widow',
                                ])
                                ->required()
                                ->live()
                                ->native(false)
                                ->default(Orphan::class)
                                ->selectablePlaceholder(false),

                            Select::make('prescribable_id')
                                ->label('Patient Name')
                                ->placeholder('Select category first')
                                ->options(function (Get $get) {
                                    $type = $get('prescribable_type');
                                    if (!$type) return [];

                                    return $type::query()
                                        ->orderBy('first_name')
                                        ->get()
                                        ->mapWithKeys(fn($patient) => [
                                            $patient->id => "{$patient->full_name} ({$patient->zone?->name})"
                                        ]);
                                })
                                ->searchable()
                                ->required()
                                ->hidden(fn(Get $get) => !$get('prescribable_type'))
                                ->native(false)
                                ->searchPrompt('Search patients by name...')
                                ->noSearchResultsMessage('No patients found.'),

                            Select::make('user_id')
                                ->label('Issuing Staff')
                                ->relationship('user', 'name')
                                ->required()
                                ->default(auth()->id())
                                ->searchable()
                                ->preload()
                                ->native(false),
                        ]),
                    ]),

                Section::make('Pharmacy & Billing')
                    ->description('Medications prescribed and associated costs.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Select::make('medications')
                            ->label('Prescribed Medications')
                            ->multiple()
                            ->relationship('medications', 'name')
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->columnSpanFull()
                            ->hint('Select drugs from the pharmacy master list')
                            ->hintIcon('heroicon-m-information-circle')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('dosage_form')
                                    ->placeholder('e.g. Tablet, Syrup, Injection'),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->default(0),
                            ]),

                        Grid::make(3)->schema([
                            TextInput::make('lab_test_cost')
                                ->label('Lab Test Cost')
                                ->numeric()
                                ->default(0)
                                ->prefix('₦')
                                ->minValue(0)
                                ->step(0.01)
                                ->required(),

                            TextInput::make('drug_cost')
                                ->label('Drug Cost')
                                ->numeric()
                                ->default(0)
                                ->prefix('₦')
                                ->minValue(0)
                                ->step(0.01)
                                ->required(),

                            TextInput::make('total_cost')
                                ->label('Total Cost')
                                ->prefix('₦')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Auto-calculated')
                                ->live()
                                ->default(fn(Get $get) =>
                                number_format(
                                    (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0),
                                    2
                                )
                                ),
                        ]),

                        Textarea::make('note')
                            ->label('Clinical Notes & Dosage Instructions')
                            ->placeholder('Enter dosage instructions, frequency, duration, or additional observations...')
                            ->rows(4)
                            ->columnSpanFull()
                            ->hint('Include dosage, frequency, and duration for each medication'),
                    ]),
            ]);
    }
}
