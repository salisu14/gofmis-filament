<?php

namespace App\Jobs;

use App\Models\IdCard;
use App\Services\IdCardPDFService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateIdCardPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public IdCard $idCard
    ) {}

    /**
     * Execute the job.
     */
    public function handle(IdCardPDFService $pdfService): void
    {
        $pdf = $pdfService->generateSingle($this->idCard);
        
        $filename = 'id-cards/pdfs/' . $this->idCard->card_number . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());
        
        $this->idCard->update([
            'pdf_path' => $filename
        ]);
    }
}
