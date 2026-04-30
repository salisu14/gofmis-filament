<?php

namespace App\Filament\Resources\Zones\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\Town;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->options(State::query()->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('city_id', null);
                                $set('town_id', null);
                            })
                            ->default(fn ($record) => $record?->town?->city?->state_id),

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
                            ->afterStateUpdated(fn ($set) => $set('town_id', null))
                            ->default(fn ($record) => $record?->town?->city_id)
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
                            ->default(fn ($record) => $record?->town_id)
                            ->disabled(fn ($get) => ! $get('city_id')),

                        Select::make('coordinator_id')
                            ->label('Coordinator')
                            ->relationship(
                                name: 'coordinator',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query) => $query->role('coordinator')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, $set, $get, $record) {

                                if (!$state) return;

                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Confirm Coordinator Change')
                                    ->body('Changing coordinator will replace the current one.')
                                    ->actions([
                                        Action::make('confirm')
                                            ->label('Confirm')
                                            ->color('danger')
                                            ->action(function () use ($state, $record) {
                                                app(\App\Services\ZoneCoordinatorService::class)
                                                    ->assignCoordinator($record, $state, auth()->id());
                                            }),

                                        Action::make('cancel')
                                            ->label('Cancel')
                                            ->close(),
                                    ])
                                    ->send();
                            }),

//                        Select::make('coordinator_id')
//                            ->label('Coordinator')
//                            ->relationship(
//                                name: 'coordinator',
//                                titleAttribute: 'name',
//                                modifyQueryUsing: fn ($query) => $query->role('coordinator')
//                            )
//                            ->searchable()
//                            ->preload()
//                            ->required()
//                            ->rules([
//                                function () {
//                                    return function ($attribute, $value, $fail) {
//
//                                        if (! $value) {
//                                            return;
//                                        }
//
//                                        // check if coordinator already assigned to another zone
//                                        $exists = \App\Models\Zone::where('coordinator_id', $value)->exists();
//
//                                        if ($exists) {
//                                            $fail('This user is already assigned to another zone.');
//                                        }
//                                    };
//                                }
//                            ])
                    ])->columns(3),


            ]);
    }
}
