<?php

namespace App\Filament\Resources\Zones\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Town;
use App\Models\Zone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Zone Identification')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->placeholder('e.g. North Sector A')
                            ->columnSpan(1),
                        Textarea::make('address')
                            ->rows(3)
                            ->placeholder('Enter full physical address...')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Location Assignment')
                    ->description('Select the geographical hierarchy for this zone.')
                    ->schema([
                        Select::make('state_id')
                            ->label('State')
                            ->options(State::pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->town) {
                                    $component->state($record->town->city?->state_id);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('city_id', null);
                                $set('town_id', null);
                            }),

                        Select::make('city_id')
                            ->label('City')
                            ->options(function (callable $get) {
                                return City::when(
                                    $get('state_id'),
                                    fn ($q) => $q->where('state_id', $get('state_id'))
                                )->pluck('name', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record && $record->town) {
                                    $component->state($record->town->city_id);
                                }
                            })
                            ->afterStateUpdated(fn ($set) => $set('town_id', null))
                            ->disabled(fn ($get) => ! $get('state_id')),

                        Select::make('town_id')
                            ->label('Town')
                            ->options(function (callable $get) {
                                return Town::when(
                                    $get('city_id'),
                                    fn ($q) => $q->where('city_id', $get('city_id'))
                                )->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state($record->town_id);
                                }
                            })
                            ->disabled(fn ($get) => ! $get('city_id')),

                        Select::make('coordinator_id')
                            ->label('Coordinator')
                            ->relationship(
                                name: 'coordinator',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->role('coordinator')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            // ✅ VALIDATION RULE: Prevent duplicate assignment at form level
                            ->rules([
                                fn (string $context, ?Zone $record) => Rule::unique('zones', 'coordinator_id')
                                    ->ignore($record?->id, 'id'),
                            ])
                            ->validationMessages([
                                'unique' => 'This coordinator is already assigned to another zone.',
                            ]),
                    ])->columns(3),
            ]);
    }
}
