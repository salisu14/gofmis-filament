<?php
// app/Filament/Widgets/IdCardPrintQueueWidget.php

namespace App\Filament\Widgets;

use App\Models\IdCardPrintBatch;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Storage;

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
                TextColumn::make('batch_name')
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

                TextColumn::make('progress_percentage')
                    ->label('Progress %')
                    ->state(fn (IdCardPrintBatch $record): int => $record->progressPercentage())
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state < 30 => 'danger',
                        $state < 70 => 'warning',
                        default => 'success',
                    })
                    ->suffix('%'),

                TextColumn::make('created_at')
                    ->since(),
            ])
            ->recordActions([
                // Use ViewAction with modal instead of URL
                ViewAction::make()
                    ->schema([
                        TextInput::make('batch_name')
                            ->disabled(),
                        TextInput::make('status')
                            ->disabled(),
                        TextInput::make('total_count')
                            ->label('Total Cards')
                            ->disabled(),
                        TextInput::make('processed_count')
                            ->label('Processed')
                            ->disabled(),
                        TextInput::make('progress_percentage')
                            ->label('Progress %')
                            ->formatStateUsing(fn($record) => $record->progressPercentage() . '%')
                            ->disabled(),
                    ]),

                // Download action (only for completed)
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn(IdCardPrintBatch $record): bool => $record->status === 'completed' && $record->pdf_path !== null
                    )
                    ->url(fn(IdCardPrintBatch $record): string => Storage::disk('public')->url($record->pdf_path)
                    )
                    ->openUrlInNewTab(),
            ])
            ->poll('5s');
    }
}
