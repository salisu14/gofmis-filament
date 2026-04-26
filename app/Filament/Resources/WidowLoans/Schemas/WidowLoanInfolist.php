<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WidowLoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('widow.id')
                    ->label('Widow'),
                TextEntry::make('amount')
                    ->numeric(),
                TextEntry::make('date_issued')
                    ->date(),
                TextEntry::make('due_date')
                    ->date()
                    ->placeholder('-'),
                IconEntry::make('fully_repaid')
                    ->boolean(),
                TextEntry::make('purpose')
                    ->placeholder('-'),
                TextEntry::make('loan_agreement_url')
                    ->placeholder('-'),
                TextEntry::make('reject_reason')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
