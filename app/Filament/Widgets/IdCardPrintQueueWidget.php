<?php
// app/Filament/Widgets/IdCardPrintQueueWidget.php

namespace App\Filament\Widgets;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use App\Models\IdCardPrintBatch;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class IdCardPrintQueueWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                IdCardPrintBatch::query()
                    ->whereIn('status', ['pending', 'processing'])
                    ->latest()
            )
            ->heading('Active Print Queue')
            ->description('ID card batches currently being processed')
            ->columns([
                Tables\Columns\TextColumn::make('batch_name')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'processing',
                    ]),

                TextColumn::make('progress')
                    ->formatStateUsing(fn($record) => "{$record->processed_count} / {$record->total_count}"
                    ),

                TextColumn::make('progressPercentage')
                    ->label('%')
                    ->formatStateUsing(fn($record) => $record->progressPercentage() . '%'),

                TextColumn::make('created_at')
                    ->since(),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn($record) => IdCardPrintBatchResource::getUrl('view', ['record' => $record])
                    )
                    ->icon('heroicon-o-eye'),
            ])
            ->poll('5s');
    }
}
