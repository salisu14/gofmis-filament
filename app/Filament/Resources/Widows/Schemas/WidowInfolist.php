<?php

namespace App\Filament\Resources\Widows\Schemas;

use App\Models\Widow;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                ImageEntry::make('picture_url')
                                    ->label('')
                                    ->disk('public')
                                    ->circular()
                                    ->defaultImageUrl(url('/images/placeholder-avatar.png')),

                                TextEntry::make('full_name')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->columnSpan(3),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('nin')->copyable(),
                                TextEntry::make('reg_no')->copyable(),
                                TextEntry::make('child_sequence'),
                            ]),

                        TextEntry::make('address')
                            ->columnSpanFull(),
                    ]),

                Section::make()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_eligible')
                                    ->boolean(),
                                IconEntry::make('is_married')
                                    ->boolean(),

                                TextEntry::make('zone.name')
                                    ->label('Zone')
                                    ->badge('success'),

                                TextEntry::make('deceased.zone.coordinator.name')
                                    ->label('Coordinator')
                                    ->badge('primary'),
                            ]),

                        TextEntry::make('married_at')
                            ->dateTime()
                            ->visible(fn(Widow $record): bool => $record->is_married),

                        TextEntry::make('skills')
                            ->badge()
                            ->separator(','),

                        TextEntry::make('deceased.full_name')
                            ->label('Deceased'),

                    ]),
            ]);
    }
}
