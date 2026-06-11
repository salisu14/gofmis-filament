<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use App\Services\IdCardPDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdCardDownloadController extends Controller
{
    public function __invoke(IdCard $idCard, Request $request)
    {
        $this->authorizeCardAccess($idCard);

        // Mark as printed if it's a real download (not just a preview)
        if (! $request->has('preview')) {
            $idCard->markAsPrinted();
        }

        $pdf = app(IdCardPDFService::class)->generateSingle($idCard);
        $output = $pdf->output();

        $filename = 'id-cards/pdfs/'.$idCard->card_number.'.pdf';
        Storage::disk('public')->put($filename, $output);
        $idCard->update(['pdf_path' => $filename]);

        if ($request->has('preview')) {
            return response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="id-card-'.$idCard->card_number.'.pdf"',
            ]);
        }

        return response()->streamDownload(
            function () use ($output): void {
                echo $output;
            },
            'id-card-'.$idCard->card_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function authorizeCardAccess(IdCard $card): void
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['admin', 'super_admin']) || $user?->can('view_id_cards')) {
            return;
        }

        $beneficiary = $card->cardable;
        $zoneId = $beneficiary?->deceased?->zone_id ?? $beneficiary?->zone_id ?? null;

        abort_unless($zoneId && $user?->managesZone($zoneId), 403);
    }
}
