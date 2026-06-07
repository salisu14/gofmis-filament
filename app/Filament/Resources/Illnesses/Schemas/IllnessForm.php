<?php

namespace App\Filament\Resources\Illnesses\Schemas;

use App\Enums\IllnessCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IllnessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Illness Details')
                    ->description('Define the illness and its classification for medical records and prescriptions.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Illness Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                Select::make('category')
                                    ->label('Category')
                                    ->options(IllnessCategory::class)
                                    ->required()
                                    ->native(false)
                                    ->searchable(),
                            ]),
                        Textarea::make('description')
                            ->label('Description / Clinical Notes')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->helperText('Optional: Include symptoms, ICD-10 codes, or specific diagnostic criteria.'),
                    ]),
            ]);
    }
}
