<?php

namespace App\Filament\Resources\WelfarePackages\Schemas;

use App\Enums\WelfarePackageStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WelfarePackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Ramadan Food Support 2026'),

                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000),

                        DatePicker::make('start_date')
                            ->required()
                            ->native(false)
                            ->minDate(now())
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('end_date', \Carbon\Carbon::parse($state)->addMonth());
                                }
                            }),

                        DatePicker::make('end_date')
                            ->required()
                            ->native(false)
                            ->minDate(fn (Get $get) => $get('start_date'))
                            ->after('start_date'),
                    ])->columns(2),

                Section::make('Items Configuration')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('category_id')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('quantity_per_family')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),

                                Textarea::make('notes')
                                    ->rows(2),
                            ])
                            ->addActionLabel('Add Item')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['item_id'] ?? 'New Item'),
                    ]),
            ]);
    }
}
