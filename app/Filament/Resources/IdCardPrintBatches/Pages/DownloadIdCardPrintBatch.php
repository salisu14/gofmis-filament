<?php
// app/Filament/Resources/IdCardPrintBatchResource/Pages/DownloadIdCardPrintBatch.php

namespace App\Filament\Resources\IdCardPrintBatches\Pages;

use App\Filament\Resources\IdCardPrintBatches\IdCardPrintBatchResource;
use Filament\Pages\Page;

class DownloadIdCardPrintBatch extends Page
{
    protected static string $resource = IdCardPrintBatchResource::class;

//    public function __invoke(IdCardPrintBatch $record)
//    {
//        if ($record->status !== 'completed' || !$record->pdf_path) {
//            abort(404, 'PDF not available');
//        }
//
//        return response()->download(
//            Storage::disk('public')->path($record->pdf_path),
//            $record->batch_name . '.pdf',
//            ['Content-Type' => 'application/pdf']
//        );
//    }
}
