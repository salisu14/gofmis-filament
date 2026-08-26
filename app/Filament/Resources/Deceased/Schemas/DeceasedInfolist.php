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
                        TextEntry::make('nin')->label('NIN'),
                        TextEntry::make('date_of_birth')->date()->label('Date of Birth'),
                        TextEntry::make('age_at_death')
                            ->label('Age at Death')
                            ->state(fn ($record) => $record->age_at_death)
                            ->suffix(' years'),
                        TextEntry::make('vulnerability_status')->badge()->label('Vulnerability Status'),
                    ])->columns(3),

                Section::make('Death Information')
                    ->schema([
                        TextEntry::make('date_registered')->date()->label('Date Registered'),
                        TextEntry::make('date_of_death')->date()->label('Date of Death'),
                        TextEntry::make('death_place')->label('Place of Death'),
                        TextEntry::make('death_cause')->label('Cause of Death'),
                        TextEntry::make('occupation')->label('Occupation'),
                        IconEntry::make('has_death_cert')
                            ->boolean()
                            ->label('Certificate Available'),
                    ])->columns(3),

                Section::make('Location & Contact')
                    ->schema([
                        TextEntry::make('zone.name')->label('Zone'),
                        TextEntry::make('coordinator.name')->label('Coordinator')->copyable(),
                        TextEntry::make('guardian_name')->label('Guardian Full Name'),
                        TextEntry::make('guardian_phone')->label('Guardian Phone')->copyable(),
                        TextEntry::make('address')->label('Family Contact Address')->columnSpanFull(),
                    ])->columns(4),

                Section::make('Family Composition at Registration')
                    ->description('Historical family composition reported at the time of GOF registration.')
                    ->schema([
                        TextEntry::make('number_of_widows_left')
                            ->label('Widows Left at Registration')
                            ->numeric(),
                        TextEntry::make('number_of_orphans_left')
                            ->label('Orphans Left at Registration')
                            ->numeric(),
                    ])->columns(2),

                Section::make('Current GOFMIS Registration')
                    ->description('Actual beneficiary records currently registered and linked in GOFMIS.')
                    ->schema([
                        TextEntry::make('widows_count')
                            ->label('Registered Widows')
                            ->state(fn ($record) => method_exists($record, 'widows') ? $record->widows()->count() : 0)
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('orphans_count')
                            ->label('Registered Orphans')
                            ->state(fn ($record) => method_exists($record, 'orphans') ? $record->orphans()->count() : 0)
                            ->badge()
                            ->color('info'),
                        TextEntry::make('eligible_orphans_count')
                            ->label('Active / Eligible Orphans')
                            ->state(fn ($record) => method_exists($record, 'eligibleOrphans') ? $record->eligibleOrphans()->count() : 0)
                            ->badge()
                            ->color('success'),
                        TextEntry::make('archived_orphans_count')
                            ->label('Archived / Overaged Orphans')
                            ->state(fn ($record) => method_exists($record, 'orphans')
                                ? $record->orphans()->where('status', 'archived')->count()
                                : 0
                            )
                            ->badge()
                            ->color('gray'),
                    ])->columns(4),
            ]);
    }
}
