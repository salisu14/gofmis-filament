<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->description('Define the classification and purpose for this category.')
                    ->icon('heroicon-m-tag')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus(),

                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name')
                            ->placeholder('Root Category (None)')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('user_id')
                            ->label('Managed By')
                            ->relationship('user', 'name')
                            ->default(auth()->id())
                            ->required()
                            ->searchable()
                            ->preload(),

                        Textarea::make('description')
                            ->label('Category Description')
                            ->placeholder('Describe the types of items that belong in this category...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
