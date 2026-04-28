{{-- resources/views/id-cards/bulk-template.blade.php --}}<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        @page { size: A4; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', Arial, sans-serif; background: #fff; }
        .page { width: 190mm; height: 277mm; position: relative; page-break-after: always; }
        .page-header { text-align: center; margin-bottom: 5mm; padding-bottom: 3mm; border-bottom: 0.1mm solid #ccc; }
        .page-header h2 { font-size: 12pt; color: #333; margin-bottom: 1mm; }
        .page-header p { font-size: 8pt; color: #666; }
        .cards-table { width: 100%; border-collapse: collapse; }
        .cards-table td { padding: 5mm 2.5mm; vertical-align: top; width: 50%; }
        .card-wrapper { width: 85.60mm; height: 53.98mm; position: relative; border: 0.1mm dashed #ccc; margin: 0 auto; }
        
        /* Partial Styles */
        .card-container { width: 85.60mm; height: 53.7mm; position: relative; padding: 3mm; border: none; overflow: hidden; }
        .header { border-bottom: 0.2mm solid {{ $accent_color }}; padding-bottom: 1mm; margin-bottom: 1.5mm; height: 13.5mm; }
        .logo-container { float: left; width: 12mm; height: 10mm; margin-right: 2mm; }
        .logo { width: 12mm; height: 10mm; object-fit: contain; }
        .foundation-info { float: left; width: 65mm; }
        .foundation-name { font-size: 7.5pt; font-weight: bold; color: {{ $accent_color }}; text-transform: uppercase; margin-bottom: 0.2mm; }
        .card-type { font-size: 5pt; color: #666; letter-spacing: 0.5px; margin-top: 0.5mm; }
        .clear { clear: both; }
        .content { margin-top: 0.5mm; height: 26mm; }
        .photo-section { float: left; width: 18mm; text-align: center; }
        .photo { width: 16mm; height: 20mm; object-fit: cover; border: 0.2mm solid #ccc; border-radius: 1mm; }
        .details { float: left; width: 42mm; font-size: 5.5pt; padding-left: 2mm; }
        .detail-row { margin-bottom: 0.8mm; }
        .label { color: #666; font-size: 4.2pt; text-transform: uppercase; margin-bottom: 0.1mm; }
        .value { color: #333; font-weight: bold; }
        .qr-section { position: absolute; right: 5mm; bottom: 5mm; width: 20mm; text-align: center; }
        .qr-code { width: 13mm; height: 13mm; border: 0.1mm solid #eee; padding: 0.5mm; background: #fff; display: block; margin: 0 auto; }
        .card-number { font-size: 4.5pt; color: #333; font-weight: bold; margin-bottom: 0.5mm; font-family: monospace; }
        .footer { position: absolute; bottom: 1.5mm; left: 3mm; width: 75mm; font-size: 4pt; color: #777; text-align: left; }
        .valid-row { margin-bottom: 0mm; }
        .security-hologram { position: absolute; top: 3mm; right: 3mm; width: 8mm; height: 8mm; background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,215,0,0.2) 50%, rgba(255,255,255,0.1) 100%); border-radius: 50%; border: 0.1mm solid rgba(0,0,0,0.05); }

        .cut-mark { position: absolute; width: 3mm; height: 3mm; border: 0.2mm solid #ff0000; }
        .cut-mark.top-left { top: -1.5mm; left: -1.5mm; border-right: none; border-bottom: none; }
        .cut-mark.top-right { top: -1.5mm; right: -1.5mm; border-left: none; border-bottom: none; }
        .cut-mark.bottom-left { bottom: -1.5mm; left: -1.5mm; border-right: none; border-top: none; }
        .cut-mark.bottom-right { bottom: -1.5mm; right: -1.5mm; border-left: none; border-top: none; }
        .print-instructions { position: absolute; bottom: 5mm; left: 10mm; right: 10mm; font-size: 7pt; color: #666; text-align: center; border-top: 0.1mm solid #eee; padding-top: 3mm; }
    </style></head><body>@foreach($pages as $pageIndex => $page)<div class="page"><div class="page-header"><h2>{{ $foundationName }} - ID Card Print Batch</h2><p>Batch: {{ $batch->batch_name }} | Page {{ $pageIndex + 1 }} of {{ count($pages) }}</p></div><table class="cards-table">@foreach(array_chunk($page['cards'], 2) as $row)<tr>@foreach($row as $card)<td><div class="card-wrapper">@if($page['showCutMarks'])<div class="cut-mark top-left"></div><div class="cut-mark top-right"></div><div class="cut-mark bottom-left"></div><div class="cut-mark bottom-right"></div>@endif @include('id-cards.card-content', $card)</div></td>@endforeach @if(count($row) == 1)<td></td>@endif</tr>@endforeach</table><div class="print-instructions">Print on CR80 PVC card stock (85.60mm x 53.98mm) using a card printer. Cut along dashed lines if using sheet stock.</div></div>@endforeach</body></html>
