<?php

namespace App\Filament\Resources\IdCardPrintBatches\Schemas;

use App\Models\IdCardPrintBatch;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdCardPrintBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Batch Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('batch_name'),
                        TextEntry::make('type')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'widow' => 'warning',
                                'orphan' => 'success',
                                'mixed' => 'info',
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending' => 'gray',
                                'processing' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                            }),
                    ]),

                Section::make('Progress')
                    ->schema([
                        TextEntry::make('total_count')
                            ->label('Total Cards'),
                        TextEntry::make('processed_count')
                            ->label('Processed'),
                        TextEntry::make('progressPercentage')
                            ->label('Completion %')
                            ->state(fn (IdCardPrintBatch $record): string => $record->progressPercentage() . '%'),
                        TextEntry::make('started_at')
                            ->dateTime('F j, Y H:i:s')
                            ->placeholder('Not started'),
                        TextEntry::make('completed_at')
                            ->dateTime('F j, Y H:i:s')
                            ->placeholder('Not completed'),
                    ]),

                Section::make('Filters Applied')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('filters')
                            ->formatStateUsing(fn($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                    ]),

                Section::make('Range')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('range')
                            ->formatStateUsing(fn($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : 'All eligible beneficiaries')
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                    ]),
            ]);
    }
}
