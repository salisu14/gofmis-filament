<?php

namespace App\Filament\Resources\Zones\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Town;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->options(State::all()->pluck('name', 'id'))
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => [
                                $set('city_id', null),
                                $set('town_id', null),
                            ]),

                        Select::make('city_id')
                            ->label('City')
                            ->options(function (callable $get) {
                                $stateId = $get('state_id');
                                if (!$stateId) return [];
                                return City::where('state_id', $stateId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('town_id', null))
                            ->disabled(fn (callable $get) => !$get('state_id')),

                        Select::make('town_id')
                            ->label('Town')
                            ->relationship('town', 'name')
                            ->options(function (callable $get) {
                                $cityId = $get('city_id');
                                if (!$cityId) return [];
                                return Town::where('city_id', $cityId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (callable $get) => !$get('city_id')),
                    ])->columns(3),
            ]);
    }
}
