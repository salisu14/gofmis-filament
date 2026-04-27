<?php

namespace App\Filament\Resources\WelfarePackages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WelfarePackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
//        $stats = app(WelfarePackageService::class)->getPackageStatistics($this->record);

        return $schema
            ->schema([
                Section::make('Package Information')
                    ->schema([
                        TextEntry::make('name')
                            ->size(TextSize::Large)
                            ->weight('font-bold'),
                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        TextEntry::make('start_date')
                            ->date('F d, Y'),
                        TextEntry::make('end_date')
                            ->date('F d, Y'),
                        TextEntry::make('creator.name')
                            ->label('Created By'),
                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->placeholder('Not yet approved'),
                    ])->columns(3),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('total_beneficiaries')
                            ->label('Total Beneficiaries'),
//                            ->state($stats['total_beneficiaries']),
                        TextEntry::make('approved')
                            ->label('Approved'),
//                            ->state($stats['approved']),
                        TextEntry::make('pending_approval')
                            ->label('Pending Approval')
                            ->state($stats['pending_approval']),
                        TextEntry::make('collected')
                            ->label('Collected')
                            ->state($stats['collected']),
                        TextEntry::make('not_collected')
                            ->label('Not Collected')
                            ->state($stats['not_collected']),
                        TextEntry::make('collection_rate')
                            ->label('Collection Rate')
                            ->state($stats['collection_rate'] . '%')
                            ->color(fn($state) => (float)$state > 75 ? 'success' : 'warning'),
                    ])->columns(3),
            ]);
    }
}
