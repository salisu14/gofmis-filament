<?php

namespace App\Filament\Resources\Sponsors\Schemas;

use App\Enums\SponsorType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sponsor Identity')
                    ->description('Primary naming and classification.')
                    ->icon('heroicon-m-user-group')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Sponsor Name / Organization')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Al-Khair Charity or John Doe')
                                ->autofocus(),

                            Select::make('type')
                                ->label('Category')
                                ->options(SponsorType::class)
                                ->required()
                                ->native(false)
                                ->hint('Determines the nature of the partnership.'),
                        ]),
                    ]),

                Section::make('Contact Information')
                    ->description('Primary communication channels for this sponsor.')
                    ->icon('heroicon-m-envelope')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->placeholder('sponsor@example.com')
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->label('Phone Number')
                                ->tel()
                                ->placeholder('+234...'),
                        ]),

                        Textarea::make('address')
                            ->label('Physical Address')
                            ->placeholder('Enter the full office or residential address...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Administrative Notes')
                    ->icon('heroicon-m-pencil-square')
                    ->schema([
                        Textarea::make('notes')
                            ->label('General Observations')
                            ->placeholder('Any historical context or specific preferences for this sponsor...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }
}
