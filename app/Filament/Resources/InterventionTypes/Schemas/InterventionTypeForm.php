<?php

namespace App\Filament\Resources\InterventionTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InterventionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Definition')
                    ->description('Manage specific types of support available (e.g. Health, Education, Food).')
                    ->icon('heroicon-m-tag')
                    ->schema([
                        TextInput::make('name')
                            ->label('Type Name')
                            ->placeholder('e.g. Educational Support')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus(),
                    ]),
            ]);
    }
}
