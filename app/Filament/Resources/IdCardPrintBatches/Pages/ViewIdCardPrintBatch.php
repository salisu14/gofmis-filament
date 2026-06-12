<?php

namespace App\Filament\Resources\IdCardPrintBatches\Pages;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use App\Models\IdCardPrintBatch;
use App\Services\IdCardPrintBatchService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewIdCardPrintBatch extends ViewRecord
{
    protected static string $resource = IdCardPrintBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview PDF')
                ->icon('heroicon-m-eye')
                ->url(fn (IdCardPrintBatch $record): string => route('id-card-print-batches.download', [
                    'record' => $record,
                    'preview' => 1,
                ]))
                ->openUrlInNewTab()
                ->visible(fn (IdCardPrintBatch $record): bool => $record->status === 'completed' && filled($record->pdf_path)),

            Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->url(fn (IdCardPrintBatch $record): string => route('id-card-print-batches.download', ['record' => $record]))
                ->openUrlInNewTab()
                ->visible(fn (IdCardPrintBatch $record): bool => $record->status === 'completed' && filled($record->pdf_path)),

            Action::make('process_now')
                ->label('Process Now')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (IdCardPrintBatch $record): bool => in_array($record->status, ['pending', 'processing', 'failed'], true))
                ->action(function (IdCardPrintBatch $record): void {
                    try {
                        app(IdCardPrintBatchService::class)->process($record);

                        Notification::make()
                            ->title('Print batch generated')
                            ->body('The printable PDF is ready for download.')
                            ->success()
                            ->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $record->fresh()]));
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Batch processing failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make()
                ->visible(fn (IdCardPrintBatch $record): bool => in_array($record->status, ['pending', 'failed'], true)),

            DeleteAction::make()
                ->visible(fn (IdCardPrintBatch $record): bool => in_array($record->status, ['pending', 'failed'], true)),
        ];
    }
}
