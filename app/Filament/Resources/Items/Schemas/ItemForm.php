<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item Details')
                    ->description('Basic information about the item.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->maxLength(65535)
                                    ->columnSpanFull()
                                    ->rows(3),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')->required()->maxLength(255),
                                    ]), // Allows creating a category on the fly

                                Select::make('user_id')
                                    ->label('User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id()) // Automatically assigns the logged-in user
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
