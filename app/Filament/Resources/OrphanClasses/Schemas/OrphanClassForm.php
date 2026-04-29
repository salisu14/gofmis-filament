<?php

namespace App\Filament\Resources\OrphanClasses\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrphanClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class Definition')
                    ->description('Define academic levels for orphans (e.g., Primary 1, JSS 2).')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        TextInput::make('name')
                            ->label('Class Name')
                            ->placeholder('e.g. JSS III')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus(),

                        // Automatically link to the staff member creating the record
                        Hidden::make('user_id')
                            ->default(auth()->id())
                            ->required(),
                    ]),
            ]);
    }
}
