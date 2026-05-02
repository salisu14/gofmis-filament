<?php

namespace App\Filament\Resources\Verifications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationVerificationInfolist
{
    /**
     * Infolist provides the clean, read-only summary for the View page.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Request Overview')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('orphan.full_name')
                            ->label('Student')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('orphan.deceased.zone.name')
                            ->label('Zone')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('requested_amount')
                            ->money('NGN'),
                    ]),

                Section::make('Justification')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Coordinator Reason')
                            ->placeholder('No justification notes provided.'),
                    ]),

                Section::make('Internal Verification Audit')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'under_review' => 'info',
                                default => 'warning',
                            }),
                        TextEntry::make('verification_status')
                            ->badge(),
                        TextEntry::make('verifier.name')
                            ->label('Assigned Verifier')
                            ->placeholder('Pending Assignment'),

                        TextEntry::make('verification_notes')
                            ->label('Verifier Remarks')
                            ->columnSpanFull()
                            ->placeholder('No verification findings recorded yet.'),
                    ]),
            ]);
    }
}
