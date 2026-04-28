<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use Illuminate\Http\Request;

class IdCardDownloadController extends Controller
{
    public function __invoke(IdCard $idCard, Request $request)
    {
        // Mark as printed if it's a real download (not just a preview)
        if (!$request->has('preview')) {
            $idCard->markAsPrinted();
        }

        $disposition = $request->has('preview') ? 'inline' : 'attachment';

        if ($idCard->pdf_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($idCard->pdf_path)) {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($idCard->pdf_path);
            
            if ($request->has('preview')) {
                return response()->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="id-card-' . $idCard->card_number . '.pdf"'
                ]);
            }

            return response()->download($path, 'id-card-' . $idCard->card_number . '.pdf');
        }

        $pdf = app(\App\Services\IdCardPDFService::class)->generateSingle($idCard);

        if ($request->has('preview')) {
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="id-card-' . $idCard->card_number . '.pdf"'
            ]);
        }

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'id-card-' . $idCard->card_number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
