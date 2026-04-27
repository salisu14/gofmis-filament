<?php

namespace App\Filament\Resources\OrphanEducation\Schemas;

use App\Models\OrphanEducation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrphanEducationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student & Institution')
                    ->description('Academic placement and enrollment details.')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('orphan.full_name')
                                ->label('Student Name')
                                ->weight('bold')
                                ->color('primary')
                                ->size('lg'),

                            TextEntry::make('institution.name')
                                ->label('Institution')
                                ->weight('semibold'),

                            TextEntry::make('level')
                                ->label('Level / Grade')
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('class_level')
                                ->label('Class Section')
                                ->placeholder('Not Assigned'),
                        ]),
                    ]),

                Section::make('Financial Setup & Accounting')
                    ->description('Billed rates vs actual payment history.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('school_fee')
                                ->label('Contracted Fee')
                                ->money('NGN'),
//                                ->description(fn ($record) => "Per {$record->fee_frequency}"),

                            TextEntry::make('total_paid')
                                ->label('Total Paid (To Date)')
                                ->state(fn (OrphanEducation $record) => $record->total_paid)
                                ->money('NGN')
                                ->color('success')
                                ->weight('bold'),

                            TextEntry::make('balance')
                                ->label('Current Balance')
                                ->state(fn (OrphanEducation $record) => $record->balance)
                                ->money('NGN')
                                ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                                ->weight('bold'),

                            IconEntry::make('is_fee_supported')
                                ->label('Sponsorship')
                                ->boolean(),
                        ]),

                        TextEntry::make('support_amount')
                            ->label('Sponsorship Contribution')
                            ->money('NGN')
                            ->visible(fn ($record) => $record->is_fee_supported)
                            ->placeholder('0.00'),
                    ]),

                Section::make('Timeline & Status')
                    ->schema([
                        Grid::make(3)->schema([
                            IconEntry::make('is_current')
                                ->label('Active Enrollment')
                                ->boolean(),

                            TextEntry::make('started_at')
                                ->label('Start Date')
                                ->date(),

                            TextEntry::make('ended_at')
                                ->label('Exit Date')
                                ->date()
                                ->placeholder('Ongoing'),
                        ]),
                    ]),

                Section::make('Audit Trail')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('id')->label('Internal ID')->fontFamily('mono')->copyable(),
                            TextEntry::make('created_at')->label('Registered')->dateTime(),
                            TextEntry::make('deleted_at')
                                ->label('Archived On')
                                ->dateTime()
                                ->visible(fn ($record) => $record->trashed()),
                        ]),
                    ]),
            ]);
    }
}
