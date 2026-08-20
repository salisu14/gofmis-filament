<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Enums\IllnessCategory;
use App\Enums\PrescriptionStatus;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(Schema $schema, bool $includePatient = true): Schema
    {
        $components = [
            Section::make('Treatment Status & Outcome')
                ->description('Status of treatment administration and completion details.')
                ->icon('heroicon-m-check-badge')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('status')
                            ->label('Treatment Status')
                            ->options(PrescriptionStatus::class)
                            ->enum(PrescriptionStatus::class)
                            ->default(PrescriptionStatus::PENDING->value)
                            ->native(false)
                            ->disabled()
                            ->dehydrated(),

                        DatePicker::make('treated_at')
                            ->label('Completed Date')
                            ->native(false)
                            ->disabled()
                            ->dehydrated(),

                        Select::make('treated_by_id')
                            ->label('Completed By')
                            ->relationship('treatedBy', 'name')
                            ->disabled()
                            ->dehydrated(),
                    ]),

                    Textarea::make('treatment_notes')
                        ->label('Treatment Outcome & Administration Notes')
                        ->rows(3)
                        ->disabled()
                        ->dehydrated()
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record?->isTreated()),
                ]),

            Section::make('Clinical Information')
                ->description('Diagnosis details and attending physician.')
                ->icon('heroicon-m-beaker')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('doctor_name')
                            ->label('Doctor Name')
                            ->placeholder('e.g. Dr. Adamu Musa')
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->required()
                            ->maxLength(255),

                        Select::make('illness_id')
                            ->label('Illness / Diagnosis')
                            ->relationship('illnessModel', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->optionsLimit(50)
                            ->getOptionLabelFromRecordUsing(function (Illness $record): string {
                                $categoryLabel = $record->category instanceof IllnessCategory ? $record->category->label() : ($record->category ?? 'General');

                                return "{$record->name} — {$categoryLabel}";
                            })
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
                            ->columnSpan(2)
                            ->required(),

                        DatePicker::make('prescription_date')
                            ->label('Date of Visit')
                            ->default(now())
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->required()
                            ->native(false)
                            ->closeOnDateSelection(),
                    ]),
                ]),
        ];

        if ($includePatient) {
            $components[] = Section::make('Patient & Provider')
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
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(
                                fn (Set $set) => $set('prescribable_id', null)
                            )
                            ->native(false)
                            ->default(Orphan::class)
                            ->selectablePlaceholder(false),

                        Select::make('prescribable_id')
                            ->label('Patient Name')
                            ->placeholder('Select patient')
                            ->disabledOn('edit')
                            ->options(function (Get $get): array {
                                $type = $get('prescribable_type');

                                if (! $type) {
                                    return [];
                                }

                                $query = $type::query()
                                    ->orderBy('first_name');

                                return $query
                                    ->get()
                                    ->mapWithKeys(function ($patient): array {
                                        $name = $patient->full_name
                                            ?: trim(collect([
                                                $patient->first_name,
                                                $patient->middle_name,
                                                $patient->last_name,
                                            ])->filter()->implode(' '))
                                                ?: 'Unnamed beneficiary';

                                        $zone = $patient->zone?->name;

                                        return [
                                            $patient->id => $zone
                                                ? "{$name} ({$zone})"
                                                : $name,
                                        ];
                                    })
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->hidden(fn (Get $get): bool => ! $get('prescribable_type'))
                            ->native(false)
                            ->searchPrompt('Search patients by name...')
                            ->noSearchResultsMessage('No patients found.'),

                        Select::make('user_id')
                            ->label('Issuing Staff')
                            ->relationship('user', 'name')
                            ->required()
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->default(auth()->id())
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ]),
                ]);
        } else {
            $components[] = Hidden::make('user_id')
                ->default(auth()->id());
        }

        $isStaff = auth()->user()?->hasAnyRole(['admin', 'super_admin']);

        $medicationSelect = Select::make('medications')
            ->label('Prescribed Medications')
            ->multiple()
            ->relationship('medications', 'name')
            ->preload()
            ->searchable()
            ->native(false)
            ->disabled(fn ($record) => $record?->isTreated())
            ->columnSpanFull()
            ->hint('Select drugs from the pharmacy master list')
            ->hintIcon('heroicon-m-information-circle');

        if ($isStaff) {
            $medicationSelect->createOptionForm([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('dosage_form')
                    ->placeholder('e.g. Tablet, Syrup, Injection'),
                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('₦')
                    ->default(0),
            ]);
        }

        $components[] = Section::make('Pharmacy & Billing')
            ->description('Medications prescribed and associated costs.')
            ->icon('heroicon-m-banknotes')
            ->schema([
                $medicationSelect,

                Grid::make(3)->schema([
                    TextInput::make('lab_test_cost')
                        ->label('Lab Test Cost')
                        ->numeric()
                        ->default(0)
                        ->prefix('₦')
                        ->disabled(fn ($record) => $record?->isTreated())
                        ->minValue(0)
                        ->step(0.01)
                        ->required(),

                    TextInput::make('drug_cost')
                        ->label('Drug Cost')
                        ->numeric()
                        ->default(0)
                        ->prefix('₦')
                        ->disabled(fn ($record) => $record?->isTreated())
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
                        ->default(fn (Get $get) => number_format(
                            (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0),
                            2
                        )
                        ),
                ]),

                Textarea::make('note')
                    ->label('Clinical Notes & Dosage Instructions')
                    ->placeholder('Enter dosage instructions, frequency, duration, or additional observations...')
                    ->disabled(fn ($record) => $record?->isTreated())
                    ->rows(4)
                    ->columnSpanFull()
                    ->hint('Include dosage, frequency, and duration for each medication'),
            ]);

        return $schema->components($components);
    }
}
