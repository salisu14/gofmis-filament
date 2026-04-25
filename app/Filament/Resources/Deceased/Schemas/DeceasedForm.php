<?php

namespace App\Filament\Resources\Deceased\Schemas;

use App\Enums\VulnerabilityStatus;
use App\Models\City;
use App\Models\Deceased;
use App\Models\State;
use App\Models\Town;
use App\Models\Zone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;

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
                                    TextInput::make('first_name')->required(),
                                    TextInput::make('middle_name'),
                                    TextInput::make('last_name')->required(),
                                ])->columns(3),

                                Group::make()->schema([
                                    TextInput::make('nin')
                                        ->label('NIN')
                                        ->minLength(11)
                                        ->maxLength(11)
                                        ->placeholder('National Identification Number'),

                                    TextInput::make('reg_no')
                                        ->label('Registration No')
                                        ->unique(ignoreRecord: true)
                                        // Lock the field if the record already exists in the database
                                        ->disabled(fn (?Deceased $record) => $record !== null)
                                        // Ensure the value is still sent to the database during creation
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The reg no cannot be changed once the Deceased is created.'),

                                    TextInput::make('occupation'),
                                    TextInput::make('age')
                                        ->numeric()
                                        ->suffix('Years'),
                                ])->columns(4),

                                Select::make('vulnerability_status')
                                    ->options(VulnerabilityStatus::class)
                                    ->required()
                                    ->native(false),
                            ]),

                        Tabs\Tab::make('Death & Documentation')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Group::make()->schema([
                                    DatePicker::make('date_registered')
                                        ->default(now()),
                                    TextInput::make('death_place'),
                                    TextInput::make('death_cause'),
                                ])->columns(3),

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
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Group::make()->schema([
                                    TextInput::make('guardian_name')
                                    ->required(),
                                    TextInput::make('guardian_phone')->tel(),
                                ])->columns(2),
                            ]),

                        Tabs\Tab::make('Dependents Stats')
                            ->icon('heroicon-m-users')
                            ->schema([
                                TextInput::make('note')
                                    ->placeholder('Enter the counts of dependents left behind.')
                                    ->columnSpanFull(),
                                TextInput::make('number_of_widows_left')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('number_of_orphans_left')
                                    ->numeric()
                                    ->default(0),
                            ])->columns(2),
                    ])->columnSpanFull()
            ]);
    }
}
