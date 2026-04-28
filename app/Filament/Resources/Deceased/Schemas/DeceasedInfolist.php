<?php

namespace App\Filament\Resources\Deceased\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeceasedInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deceased Profile')
                    ->schema([
                        TextEntry::make('full_name')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('reg_no')
                            ->label('Registration Number')
                            ->placeholder('Auto-generated')
                            ->copyable()
                            ->color('primary')
                            ->weight('bold')
                            ->disabled()
                            ->dehydrated(false),
                        TextEntry::make('vulnerability_status')->badge(),
                        TextEntry::make('nin')->label('NIN'),
                    ])->columns(4),

                Section::make('Death Information')
                    ->schema([
                        TextEntry::make('date_registered')->date(),
                        TextEntry::make('death_place'),
                        TextEntry::make('death_cause'),
                        IconEntry::make('has_death_cert')
                            ->boolean()
                            ->label('Certificate Available'),
                    ])->columns(4),

                Section::make('Location & Contact')
                    ->schema([
                        TextEntry::make('zone.name')->label('Zone'),
                        TextEntry::make('address')->columnSpanFull(),
                        TextEntry::make('guardian_name')->label('Guardian'),
                        TextEntry::make('guardian_phone')->copyable(),
                    ])->columns(3),

                Section::make('Dependents Statistics')
                    ->description('Counts of widows and orphans as reported at registration.')
                    ->schema([
                        TextEntry::make('number_of_widows_left')
                            ->label('Widows Reported')
                            ->numeric(),
                        TextEntry::make('widows_count')
                            ->label('Widows Registered')
                            ->state(fn($record) => method_exists($record, 'widows')
                                ? $record->widows()->count()
                                : 0
                            )
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('number_of_orphans_left')
                            ->label('Orphans Reported')
                            ->numeric(),
                        TextEntry::make('orphans_count')
                            ->label('Orphans Registered')
                            ->state(fn($record) => method_exists($record, 'orphans')
                                ? $record->orphans()->count()
                                : 0
                            )
                            ->badge()
                            ->color('info'),
                    ])->columns(4),
            ]);
    }
}
