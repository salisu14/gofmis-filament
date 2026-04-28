<?php

namespace App\Filament\Resources\ZoneTransfers\Schemas;

use App\Models\Deceased;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                                ->label('Household Head (Deceased)')
                                ->relationship('deceased', 'full_name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name} ({$record->reg_no})")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                // Auto-fill the current zone when a household is selected
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    if (!$state) {
                                        $set('from_zone_id', null);
                                        return;
                                    }

                                    $deceased = Deceased::find($state);
                                    if ($deceased) {
                                        $set('from_zone_id', $deceased->zone_id);
                                    }
                                }),

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
