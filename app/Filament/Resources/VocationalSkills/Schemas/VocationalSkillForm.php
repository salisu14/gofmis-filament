<?php

namespace App\Filament\Resources\VocationalSkills\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VocationalSkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Skill Definition')
                    ->description('Specify the generic name for this vocational training category.')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        TextInput::make('name')
                            ->label('Skill Name')
                            ->placeholder('e.g. Tailoring, Carpentry, Web Development')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus(),
                    ]),
            ]);
    }
}
