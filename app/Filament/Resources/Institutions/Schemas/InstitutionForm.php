<?php

namespace App\Filament\Resources\Institutions\Schemas;

use App\Enums\InstitutionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Institution Identity')
                    ->description('General information about the educational or vocational facility.')
                    ->icon('heroicon-m-building-office')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Al-Iman International School')
                            ->autofocus(),

                        Select::make('type')
                            ->options(InstitutionType::class)
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->hint('The primary focus of this facility.'),

                        Textarea::make('address')
                            ->label('Physical Address')
                            ->placeholder('Enter the full street address and landmarks...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
