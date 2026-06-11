<?php

namespace App\Http\Controllers;

use App\Models\IdCardPrintBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdCardPrintBatchDownloadController extends Controller
{
    public function __invoke(IdCardPrintBatch $record, Request $request)
    {
        $user = auth()->user();

        abort_unless(
            $user?->hasAnyRole(['admin', 'super_admin'])
            || $user?->can('view_id_cards')
            || $record->created_by === $user?->id,
            403
        );

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
