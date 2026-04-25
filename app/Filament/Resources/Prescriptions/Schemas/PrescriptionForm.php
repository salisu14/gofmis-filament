<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Models\Orphan;
use App\Models\Widow;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                    ->description('Details regarding the diagnosis and the attending physician.')
                    ->icon('heroicon-m-beaker')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('doctor_name')
                                ->label('Doctor Name')
                                ->placeholder('e.g. Dr. Adamu')
                                ->required(),

                            TextInput::make('illness')
                                ->label('Diagnosis / Illness')
                                ->placeholder('e.g. Typhoid Fever')
                                ->required(),

                            DatePicker::make('prescription_date')
                                ->label('Date of Visit')
                                ->default(now())
                                ->required()
                                ->native(false),
                        ]),
                    ]),

                Section::make('Patient & Provider')
                    ->description('Identify the beneficiary and the staff issuing the record.')
                    ->schema([
                        Grid::make(3)->schema([
                            // Handling Polymorphic Selection in a standalone resource
                            Select::make('prescribable_type')
                                ->label('Patient Category')
                                ->options([
                                    Orphan::class => 'Orphan',
                                    Widow::class => 'Widow',
                                ])
                                ->required()
                                ->live()
                                ->native(false),

                            Select::make('prescribable_id')
                                ->label('Patient Name')
                                ->placeholder('Select category first')
                                ->options(function (Get $get) {
                                    $type = $get('prescribable_type');
                                    if (! $type) return [];

                                    return $type::query()->pluck('full_name', 'id');
                                })
                                ->searchable()
                                ->required()
                                ->hidden(fn (Get $get) => ! $get('prescribable_type')),

                            Select::make('user_id')
                                ->label('Staff Member')
                                ->relationship('user', 'name')
                                ->required()
                                ->default(auth()->id())
                                ->searchable(),
                        ]),
                    ]),

                Section::make('Pharmacy & Billing')
                    ->description('Medications prescribed and associated costs.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Select::make('medications')
                            ->multiple()
                            ->relationship('medications', 'name')
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->hint('Drugs selected from the pharmacy master list.'),

                        Grid::make(2)->schema([
                            TextInput::make('lab_test_cost')
                                ->label('Lab Test Cost')
                                ->numeric()
                                ->default(0)
                                ->prefix('₦')
                                ->required(),

                            TextInput::make('drug_cost')
                                ->label('Drug Cost')
                                ->numeric()
                                ->default(0)
                                ->prefix('₦')
                                ->required(),
                        ]),

                        Textarea::make('note')
                            ->label('Clinical Notes & Dosage')
                            ->placeholder('Enter dosage instructions or additional observations...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
