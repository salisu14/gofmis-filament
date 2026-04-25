<?php

namespace App\Filament\Resources\Medications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Medication Entry')
                    ->description('Define a new drug for the pharmacy master list.')
                    ->icon('heroicon-m-beaker')
                    ->schema([
                        TextInput::make('name')
                            ->label('Generic/Brand Name')
                            ->placeholder('e.g. Paracetamol or Amoxicillin')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->autofocus(),

                        Select::make('user_id')
                            ->label('Registered By')
                            ->relationship('user', 'name')
                            ->default(auth()->id())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->hint('The staff member responsible for this entry.'),

                        Textarea::make('description')
                            ->label('Usage Instructions / Description')
                            ->placeholder('Optional notes about the drug class, common side effects, or general usage...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
