<?php

namespace App\Filament\Resources\Orphans\Schemas;

use App\Models\Deceased;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrphanForm
{
    public static function configure(Schema $schema, bool $includeDeceased = true): Schema
    {
        // Only archived (terminal) orphan records are treated as immutable.
        // A non-archived record - even one that is temporarily ineligible or
        // pending review - must remain editable so staff can correct data or
        // re-enable eligibility. The model's saving hook still enforces the
        // hard disqualifiers (over-age male, married female) on save.
        $isArchived = fn ($record) => $record?->status === \App\Enums\OrphanStatus::ARCHIVED;

        $personalSchema = [
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
                Select::make('gender')
                    ->label('Gender')
                    ->options(\App\Enums\Gender::class)
                    ->required()
                    ->native(false)
                    ->disabled($isArchived),

                Toggle::make('has_nin')
                    ->label('Has NIN?')
                    ->helperText('Enable if this beneficiary has a valid 11-digit National Identification Number.')
                    ->live()
                    ->default(false)
                    ->inline(false)
                    ->disabled($isArchived)
                    ->columnSpanFull()
                    ->afterStateUpdated(function ($state, $set) {
                        if (! $state) {
                            $set('nin', null);
                        }
                    }),

                TextInput::make('nin')
                    ->label('NIN')
                    ->string()
                    ->regex('/^[0-9]{11}$/')
                    ->unique(ignoreRecord: true)
                    ->disabled($isArchived)
                    ->required(fn ($get) => $get('has_nin'))
                    ->visible(fn ($get) => $get('has_nin'))
                    ->placeholder('11-digit NIN'),

                TextInput::make('reg_no')
                    ->label('Registration Number')
                    ->placeholder('Auto-generated on creation')
                    ->disabled()
                    ->dehydrated(false),
            ]),

            Grid::make(2)->schema([
                DatePicker::make('birth_date')
                    ->label('Date of Birth')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->disabled($isArchived)
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
                    ->dehydrated(false)
                    ->helperText('Auto-calculated from birth date.'),
            ]),
        ];

        if ($includeDeceased) {
            $personalSchema[] = Select::make('deceased_id')
                ->label('Deceased')
                ->relationship(
                    name: 'deceased',
                    titleAttribute: 'id',
                    modifyQueryUsing: fn ($query) => $query
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Deceased $record): string => trim(
                        collect([
                            $record->first_name,
                            $record->middle_name,
                            $record->last_name,
                        ])
                            ->filter(fn ($value) => filled($value))
                            ->implode(' ')
                    ) ?: 'Unnamed deceased record'
                )
                ->searchable([
                    'first_name',
                    'middle_name',
                    'last_name',
                ])
                ->preload()
                ->disabled($isArchived)
                ->required();
        } else {
            $personalSchema[] = TextInput::make('deceased_family_display')
                ->label('Deceased Household / Family')
                ->placeholder('Linked to currently viewed Deceased household')
                ->disabled()
                ->dehydrated(false);
        }

        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary identification and demographic details.')
                    ->icon('heroicon-m-user-circle')
                    ->schema($personalSchema),

                Section::make('Status & Skills')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_eligible')
                                ->label('Eligible for Support')
                                ->default(true)
                                ->disabled($isArchived)
                                ->inline(false),

                            Toggle::make('is_married')
                                ->label('Remarried')
                                ->default(false)
                                ->disabled($isArchived)
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
                            ->disabled($isArchived)
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
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->avatar()
                            ->directory('orphans')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->circleCropper()
                            ->maxSize(5120)
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
                                    ->visibility('public')
                                    ->acceptedFileTypes(['application/pdf', 'image/*']),
                            ])->columns(2),
                    ]),
            ]);
    }
}
