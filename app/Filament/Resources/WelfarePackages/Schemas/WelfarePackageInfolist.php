<?php

namespace App\Filament\Resources\WelfarePackages\Schemas;

use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Services\WelfarePackageService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WelfarePackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
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
                            ->color(fn ($state) => $state?->color()),

                        TextEntry::make('start_date')
                            ->date('F d, Y'),

                        TextEntry::make('end_date')
                            ->date('F d, Y'),

                        TextEntry::make('creator.name')
                            ->label('Created By'),

                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->placeholder('Not yet approved'),
                    ])
                    ->columns(3),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('total_beneficiaries')
                            ->label('Total Beneficiaries')
                            ->state(fn ($record) => self::getStats($record)['total_beneficiaries'] ?? 0),

                        TextEntry::make('approved')
                            ->label('Approved')
                            ->state(fn ($record) => self::getStats($record)['approved'] ?? 0),

                        TextEntry::make('pending_approval')
                            ->label('Pending Approval')
                            ->state(fn ($record) => self::getStats($record)['pending_approval'] ?? 0),

                        TextEntry::make('collected')
                            ->label('Collected')
                            ->state(fn ($record) => self::getStats($record)['collected'] ?? 0),

                        TextEntry::make('not_collected')
                            ->label('Not Collected')
                            ->state(fn ($record) => self::getStats($record)['not_collected'] ?? 0),

                        TextEntry::make('collection_rate')
                            ->label('Collection Rate')
                            ->state(function ($record) {
                                $rate = self::getStats($record)['collection_rate'] ?? 0;
                                return $rate . '%';
                            })
                            ->color(fn ($state) => (float) $state > 75 ? 'success' : 'warning'),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Cache stats per record to avoid repeated service calls
     */
    protected static function getStats($record): array
    {
        static $cache = [];

        $package = match (true) {
            $record instanceof WelfarePackage => $record,
            $record instanceof WelfareBeneficiary => $record->welfarePackage,
            default => null,
        };

        if (! $package) {
            return [];
        }

        if (! isset($cache[$package->id])) {
            $cache[$package->id] = app(WelfarePackageService::class)
                ->getPackageStatistics($package);
        }

        return $cache[$package->id];
    }
//    protected static function getStats($record): array
//    {
//        static $cache = [];
//
//        if (!isset($cache[$record->id])) {
//            $cache[$record->id] = app(WelfarePackageService::class)
//                ->getPackageStatistics($record);
//        }
//
//        return $cache[$record->id];
//    }
}
