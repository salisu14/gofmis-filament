{{-- resources/views/id-cards/template.blade.php --}}<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 85.60mm; line-height: 1; font-family: 'Helvetica', Arial, sans-serif; overflow: hidden; background: #fff; }
        .print-page { width: 85.60mm; height: 53.98mm; overflow: hidden; position: relative; }
        .card-container { width: 85.60mm; height: 53.7mm; border: none; position: relative; padding: 3mm; overflow: hidden; }
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
        .footer { position: absolute; bottom: 1.5mm; left: 3mm; width: 75mm; font-size: 4pt; color: #777; }
        .valid-row { margin-bottom: 0mm; }
        .security-hologram { position: absolute; top: 3mm; right: 3mm; width: 8mm; height: 8mm; background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,215,0,0.2) 50%, rgba(255,255,255,0.1) 100%); border-radius: 50%; border: 0.1mm solid rgba(0,0,0,0.05); }
    </style></head><body><div class="print-page">@include('id-cards.card-content')</div></body></html>
