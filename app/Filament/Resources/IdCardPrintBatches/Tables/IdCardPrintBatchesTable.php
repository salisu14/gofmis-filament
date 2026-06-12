<?php

namespace App\Filament\Resources\IdCardPrintBatches\Tables;

use App\Models\IdCardPrintBatch;
use App\Services\IdCardPrintBatchService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IdCardPrintBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_name')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'warning' => 'widow',
                        'success' => 'orphan',
                        'info' => 'mixed',
                    ]),

                TextColumn::make('total_count')
                    ->label('Total Cards')
                    ->numeric(),

                TextColumn::make('processed_count')
                    ->label('Processed')
                    ->numeric(),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (IdCardPrintBatch $record): string => $record->progressPercentage() . '%')
                    ->icon('heroicon-o-chart-bar'),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'widow' => 'Widows',
                        'orphan' => 'Orphans',
                        'mixed' => 'Mixed',
                    ]),
                Filter::make('created_today')
                    ->label('Created Today')
                    ->query(fn($query) => $query->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn(IdCardPrintBatch $record): bool => $record->status === 'completed' && $record->pdf_path !== null
                    )
                    ->url(fn(IdCardPrintBatch $record): string => route('id-card-print-batches.download', ['record' => $record])
                    )
                    ->openUrlInNewTab(),

                Action::make('process_now')
                    ->label('Process Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn(IdCardPrintBatch $record): bool => in_array($record->status, ['pending', 'processing', 'failed'], true))
                    ->action(function (IdCardPrintBatch $record) {
                        try {
                            app(IdCardPrintBatchService::class)->process($record);

                            Notification::make()
                                ->title('Print batch generated')
                                ->body('The printable PDF is ready for download.')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Batch processing failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn(IdCardPrintBatch $record): bool => in_array($record->status, ['pending', 'failed'])
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s'); // Auto-refresh for processing batches
    }
}
