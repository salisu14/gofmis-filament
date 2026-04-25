<?php

namespace App\Filament\Resources\Sponsorships\Schemas;

use App\Models\Sponsorship;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorshipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sponsorship Overview')
                    ->schema([
                        TextEntry::make('orphan.full_name')
                            ->label('Beneficiary')
                            ->weight('bold')
                            ->size('lg')
                            ->color('primary'),

                        TextEntry::make('sponsor_name')
                            ->label('Sponsor')
                            ->weight('bold'),

                        TextEntry::make('amount_committed')
                            ->label('Committed Amount')
                            ->money('NGN')
                            ->color('success')
                            ->weight('bold'),
                    ])->columns(3),

                Section::make('Duration & Notes')
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Start Date')
                            ->date(),
                        TextEntry::make('end_date')
                            ->label('End Date')
                            ->date()
                            ->placeholder('Open Ended'),
                        TextEntry::make('notes')
                            ->label('Administrative Notes')
                            ->placeholder('No notes provided.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Audit Information')
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Archived On')
                            ->dateTime()
                            ->visible(fn ($record) => $record->trashed()),
                    ])->columns(3),
            ]);
    }
}
