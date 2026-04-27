<?php

namespace App\Filament\Resources\Orphans\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrphanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary identification and demographic details.')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('first_name')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('middle_name')
                                ->maxLength(100),
                            TextInput::make('last_name')
                                ->required()
                                ->maxLength(100),
                        ]),

                        Grid::make(3)->schema([
                            DatePicker::make('birth_date')
                                ->label('Date of Birth')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    if ($state) {
                                        $set('age', \Carbon\Carbon::parse($state)->age);
                                    }
                                }),

                            TextInput::make('age')
                                ->label('Calculated Age')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated(false) // 👈 important (don’t save manually)
                                ->helperText('Auto-calculated from birth date.'),

                            TextInput::make('nin')
                                ->label('NIN')
                                ->unique(ignoreRecord: true)
                                ->placeholder('11-digit NIN')
                                ->maxLength(11)
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('reg_no')
                                ->label('Registration Number')
                                ->placeholder('Auto-generated on creation')
                                ->disabled()
                                ->dehydrated(false),

                            Select::make('deceased_id')
                                ->label('Deceased')
                                ->relationship('deceased', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),

                Section::make('Status & Skills')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_eligible')
                                ->label('Eligible for Support')
                                ->default(true)
                                ->inline(false),

                            Toggle::make('is_married')
                                ->label('Remarried')
                                ->default(false)
                                ->live()
                                ->inline(false),

                            TextInput::make('child_sequence')
                                ->label('Sequence Order')
                                ->placeholder('Auto-calculated')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                        ]),

                        DatePicker::make('married_at')
                            ->label('Date of New Marriage')
                            ->visible(fn ($get) => $get('is_married'))
                            ->required(fn ($get) => $get('is_married'))
                            ->native(false),

                        TagsInput::make('skills')
                            ->label('Vocational Skills / Profession')
                            ->placeholder('Add a skill...')
                            ->separator(',')
                            ->columnSpanFull(),
                    ]),

                Section::make('Location & Documents')
                    ->icon('heroicon-m-home-modern')
                    ->schema([
                        Textarea::make('address')
                            ->label('Residential Address')
                            ->placeholder('Enter detailed address with landmarks...')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),

                        FileUpload::make('picture_url')
                            ->label('Profile Picture')
                            ->image()
                            ->avatar()
                            ->directory('widow-photos')
                            ->disk('public')
                            ->columnSpanFull(),

                        Section::make('Birth Certificate')
                            ->compact()
                            ->schema([
                                Toggle::make('has_birth_cert')
                                    ->label('Has Birth Certificate?')
                                    ->live(),
                                FileUpload::make('birth_certificate_path')
                                    ->label('Certificate Scan')
                                    ->visible(fn ($get) => $get('has_birth_cert'))
                                    ->directory('birth-certificates')
                                    ->disk('public')
                                    ->acceptedFileTypes(['application/pdf', 'image/*']),
                            ])->columns(2),
                    ]),
            ]);
    }
}
