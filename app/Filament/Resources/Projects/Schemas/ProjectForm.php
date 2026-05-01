<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Deceased;
use App\Models\Zone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Project Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->options(collect(ProjectType::cases())
                                ->mapWithKeys(fn($t) => [$t->value => $t->label()]))
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->options(collect(ProjectStatus::cases())
                                ->mapWithKeys(fn($s) => [$s->value => $s->label()]))
                            ->default(ProjectStatus::PLANNING->value)
                            ->required()
                            ->native(false),

                        Select::make('zone_id')
                            ->label('Zone')
                            ->options(Zone::pluck('name', 'id'))
                            ->searchable()->required(),

                        Select::make('deceased_id')
                            ->label('Beneficiary Family')
                            ->options(Deceased::pluck('full_name', 'id'))
                            ->searchable()->nullable(),

                        Select::make('coordinator_id')
                            ->label('Project Coordinator')
                            ->relationship(
                                name: 'coordinator',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query) => $query->role('coordinator')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('budget_allocated')
                            ->label('Budget (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)->required(),

                        DatePicker::make('start_date'),

                        DatePicker::make('expected_completion_date'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('location_address')
                            ->label('Location')
                            ->rows(1)
                            ->columnSpanFull(),
                    ]),

                Section::make('Additional Notes')
                    ->schema([
                        RichEditor::make('notes')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),
                    ]),

            ]);
    }
}
