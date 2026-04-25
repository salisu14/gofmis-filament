<?php

namespace App\Filament\Resources\OrphanEducation\Schemas;

use App\Models\OrphanEducation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrphanEducationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('orphan.id')
                    ->label('Orphan'),
                TextEntry::make('institution.name')
                    ->label('Institution'),
                TextEntry::make('school_fee')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('fee_frequency')
                    ->placeholder('-'),
                IconEntry::make('is_fee_supported')
                    ->boolean(),
                TextEntry::make('support_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('level')
                    ->placeholder('-'),
                TextEntry::make('class_level')
                    ->placeholder('-'),
                IconEntry::make('is_current')
                    ->boolean(),
                TextEntry::make('started_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('ended_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (OrphanEducation $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
