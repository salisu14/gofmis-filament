<?php

namespace App\Filament\Resources\ZoneTransfers\Schemas;

use App\Models\Deceased;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ZoneTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transfer Details')
                    ->description('Identify the household and the destination for the relocation.')
                    ->icon('heroicon-m-arrows-right-left')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('deceased_id')
                                ->label('Deceased')
                                ->relationship(
                                    name: 'deceased',
                                    titleAttribute: 'id',
                                    modifyQueryUsing: fn ($query) => $query
                                        ->orderBy('first_name')
                                        ->orderBy('last_name')
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (Deceased $record): string => $record->full_name
                                        ?: trim(
                                            collect([
                                                $record->first_name,
                                                $record->middle_name,
                                                $record->last_name,
                                            ])
                                                ->filter()
                                                ->implode(' ')
                                        )
                                        ?: 'Unnamed deceased record'
                                )
                                ->searchable([
                                    'full_name',
                                    'first_name',
                                    'middle_name',
                                    'last_name',
                                ])
                                ->preload(),

                            Select::make('moved_by')
                                ->label('Authorized By')
                                ->relationship('mover', 'name')
                                ->default(auth()->id())
                                ->required()
                                ->searchable()
                                ->preload()
                                ->hint('Staff member recording this move.'),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('from_zone_id')
                                ->label('Current Zone (From)')
                                ->relationship('fromZone', 'name')
                                ->placeholder('No current zone assigned')
                                ->disabled() // Read-only to maintain audit integrity
                                ->dehydrated(),

                            Select::make('to_zone_id')
                                ->label('New Zone (To)')
                                ->relationship('toZone', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->hint('The zone the household is relocating to.')
                                // Prevent transferring to the same zone
                                ->disableOptionWhen(fn ($value, Get $get) => $value === $get('from_zone_id')),
                        ]),

                        Textarea::make('reason')
                            ->label('Reason for Transfer')
                            ->placeholder('e.g., Family relocated to follow work opportunities or housing change...')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
