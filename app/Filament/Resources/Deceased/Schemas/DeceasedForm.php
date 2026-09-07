<?php

namespace App\Filament\Resources\Deceased\Schemas;

use App\Enums\VulnerabilityStatus;
use App\Models\City;
use App\Models\Deceased;
use App\Models\State;
use App\Models\Town;
use App\Models\Zone;
use App\Services\Deceased\CauseOfDeathCatalog;
use App\Services\Deceased\PlaceOfDeathCatalog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DeceasedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Deceased Details')
                    ->tabs([
                        Tabs\Tab::make('Personal Information')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Group::make()->schema([
                                    TextInput::make('first_name')
                                        ->label('First Name')
                                        ->required()
                                        ->maxLength(100),
                                    TextInput::make('middle_name')
                                        ->label('Middle Name')
                                        ->maxLength(100),
                                    TextInput::make('last_name')
                                        ->label('Last Name')
                                        ->required()
                                        ->maxLength(100),
                                ])->columns(3),

                                Group::make()->schema([
                                    Toggle::make('has_nin')
                                        ->label('Has NIN?')
                                        ->helperText('Enable if this beneficiary has a valid 11-digit National Identification Number.')
                                        ->live()
                                        ->default(false)
                                        ->inline(false)
                                        ->afterStateUpdated(function ($state, $set) {
                                            if (! $state) {
                                                $set('nin', null);
                                            }
                                        })
                                        ->columnSpanFull(),

                                    TextInput::make('nin')
                                        ->label('NIN')
                                        ->string()
                                        ->regex('/^[0-9]{11}$/')
                                        ->unique(ignoreRecord: true)
                                        ->required(fn ($get) => $get('has_nin'))
                                        ->visible(fn ($get) => $get('has_nin'))
                                        ->placeholder('National Identification Number')
                                        ->helperText('Enter the complete 11-digit National Identification Number.'),

                                    TextInput::make('reg_no')
                                        ->label('Registration Number')
                                        ->unique(ignoreRecord: true)
                                        ->disabled(fn (?Deceased $record) => $record !== null)
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The registration number cannot be changed once created.'),

                                    TextInput::make('occupation')
                                        ->label('Occupation'),

                                    DatePicker::make('date_of_birth')
                                        ->label('Date of Birth')
                                        ->maxDate('today')
                                        ->live()
                                        ->native(false),

                                    TextInput::make('age')
                                        ->label('Age at Death (if DOB unknown)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('Years')
                                        ->helperText('Enter only when Date of Birth is unknown.')
                                        ->visible(fn ($get) => blank($get('date_of_birth'))),
                                ])->columns(5),

                                Select::make('vulnerability_status')
                                    ->label('Vulnerability Status')
                                    ->options(VulnerabilityStatus::class)
                                    ->required()
                                    ->native(false),
                            ]),

                        Tabs\Tab::make('Death & Documentation')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Group::make()->schema([
                                    DatePicker::make('date_registered')
                                        ->label('Date Registered')
                                        ->default(now())
                                        ->required()
                                        ->native(false),

                                    DatePicker::make('date_of_death')
                                        ->label('Date of Death')
                                        ->maxDate('today')
                                        ->afterOrEqual('date_of_birth')
                                        ->required()
                                        ->native(false),

                                    Select::make('death_place')
                                        ->label('Place of Death')
                                        ->options(PlaceOfDeathCatalog::options())
                                        ->searchable()
                                        ->live()
                                        ->afterStateHydrated(function ($component, $state, $record, $set) {
                                            if (! $record || ! $record->death_place) {
                                                return;
                                            }
                                            $val = $record->death_place;
                                            if (in_array($val, PlaceOfDeathCatalog::CANONICAL_PLACES, true)) {
                                                $set('death_place', $val);
                                            } elseif (str_starts_with($val, 'Other — ')) {
                                                $set('death_place', 'Other');
                                                $set('death_place_other', Str::after($val, 'Other — '));
                                            } else {
                                                $set('death_place', 'Other');
                                                $set('death_place_other', $val);
                                            }
                                        })
                                        ->dehydrateStateUsing(function ($state, $get) {
                                            if ($state === 'Other' && filled($get('death_place_other'))) {
                                                $other = trim((string) $get('death_place_other'));

                                                return str_starts_with($other, 'Other — ') ? $other : "Other — {$other}";
                                            }

                                            return $state;
                                        }),

                                    Select::make('death_cause')
                                        ->label('Cause of Death')
                                        ->options(CauseOfDeathCatalog::options())
                                        ->searchable()
                                        ->live()
                                        ->afterStateHydrated(function ($component, $state, $record, $set) {
                                            if (! $record || ! $record->death_cause) {
                                                return;
                                            }
                                            $val = $record->death_cause;
                                            if (CauseOfDeathCatalog::isCanonical($val)) {
                                                $set('death_cause', $val);
                                            } elseif (str_starts_with($val, 'Other — ')) {
                                                $set('death_cause', 'Other');
                                                $set('death_cause_other', Str::after($val, 'Other — '));
                                            } else {
                                                $set('death_cause', 'Other');
                                                $set('death_cause_other', $val);
                                            }
                                        })
                                        ->dehydrateStateUsing(function ($state, $get) {
                                            if ($state === 'Other' && filled($get('death_cause_other'))) {
                                                $other = trim((string) $get('death_cause_other'));

                                                return str_starts_with($other, 'Other — ') ? $other : "Other — {$other}";
                                            }

                                            return $state;
                                        }),
                                ])->columns(4),

                                Group::make()->schema([
                                    TextInput::make('death_place_other')
                                        ->label('Other Place of Death / Specify')
                                        ->placeholder('e.g. Murtala Muhammad Specialist Hospital')
                                        ->visible(fn ($get) => $get('death_place') === 'Other')
                                        ->required(fn ($get) => $get('death_place') === 'Other')
                                        ->dehydrated(false),

                                    TextInput::make('death_cause_other')
                                        ->label('Other Cause of Death / Specify')
                                        ->placeholder('e.g. Industrial Injury')
                                        ->visible(fn ($get) => $get('death_cause') === 'Other')
                                        ->required(fn ($get) => $get('death_cause') === 'Other')
                                        ->dehydrated(false),
                                ])->columns(2),

                                Section::make('Death Certificate')
                                    ->compact()
                                    ->schema([
                                        Toggle::make('has_death_cert')
                                            ->label('Has Death Certificate?')
                                            ->reactive(),
                                        FileUpload::make('death_cert_url')
                                            ->label('Certificate Scan')
                                            ->visible(fn ($get) => $get('has_death_cert'))
                                            ->directory('death-certs'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Location & Guardian')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('state_id')
                                        ->label('State')
                                        ->options(State::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->reactive()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn ($set) => $set('city_id', null)),

                                    Select::make('city_id')
                                        ->label('City')
                                        ->options(fn ($get) => City::where('state_id', $get('state_id'))->pluck('name', 'id'))
                                        ->searchable()
                                        ->reactive()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn ($set) => $set('town_id', null)),

                                    Select::make('town_id')
                                        ->label('Town')
                                        ->options(fn ($get) => Town::where('city_id', $get('city_id'))->pluck('name', 'id'))
                                        ->searchable()
                                        ->reactive()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(fn ($set) => $set('zone_id', null)),

                                    Select::make('zone_id')
                                        ->label('Zone')
                                        ->options(fn ($get) => Zone::where('town_id', $get('town_id'))->pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->relationship('zone', 'name')
                                        ->columnSpanFull(),
                                ]),

                                Textarea::make('address')
                                    ->label('Family Contact Address')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Group::make()->schema([
                                    TextInput::make('guardian_name')
                                        ->label('Guardian Full Name')
                                        ->required(),
                                    TextInput::make('guardian_phone')
                                        ->label('Guardian Phone')
                                        ->tel(),
                                ])->columns(2),
                            ]),

                        Tabs\Tab::make('Dependents Stats')
                            ->icon('heroicon-m-users')
                            ->schema([
                                TextInput::make('number_of_widows_left')
                                    ->label('Widows Left at Registration')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('number_of_orphans_left')
                                    ->label('Orphans Left at Registration')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->default(0),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
