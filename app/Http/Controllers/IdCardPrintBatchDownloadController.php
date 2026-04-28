<?php

namespace App\Http\Controllers;

use App\Models\IdCardPrintBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdCardPrintBatchDownloadController extends Controller
{
    public function __invoke(IdCardPrintBatch $record, Request $request)
    {
        $disposition = $request->has('preview') ? 'inline' : 'attachment';

        if ($record->pdf_path && Storage::disk('public')->exists($record->pdf_path)) {
            $path = Storage::disk('public')->path($record->pdf_path);
            
            if ($request->has('preview')) {
                return response()->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="batch-' . $record->id . '.pdf"'
                ]);
            }

            return response()->download($path, 'batch-' . $record->id . '.pdf');
        }

        return abort(404, 'PDF not found or still processing.');
    }
}
