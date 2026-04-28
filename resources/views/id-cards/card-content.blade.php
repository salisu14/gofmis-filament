{{-- resources/views/id-cards/card-content.blade.php --}}
<div class="card-container" style="background: linear-gradient(135deg, {{ $background_color }} 0%, #ffffff 100%);">
    <div class="security-hologram"></div>
    <div class="header">
        <div class="logo-container">
            @if($foundation_logo)
                <img src="{{ $foundation_logo }}" class="logo" alt="Logo">
            @else
                <div class="logo" style="background: {{ $accent_color }}; border-radius: 50%;"></div>
            @endif
        </div>
        <div class="foundation-info">
            <div class="foundation-name">{{ $foundation_name }}</div>
            <div class="foundation-address" style="font-size: 3.5pt; color: #888; font-weight: normal; text-transform: none;">{{ $foundation_address }}</div>
            <div class="card-type">{{ $card_type }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <div class="content">
        <div class="photo-section">
            @if($photo_url)
                <img src="{{ $photo_url }}" class="photo" alt="Photo">
            @else
                <div class="photo" style="background: #f5f5f5; border: 0.1mm dashed #ccc; line-height: 20mm; font-size: 4pt; color: #999;">NO PHOTO</div>
            @endif
        </div>
        <div class="details">
            <div class="detail-row">
                <div class="label">Name</div>
                <div class="value">{{ $full_name }}</div>
            </div>
            <div class="detail-row">
                <div class="label">ID / NIN</div>
                <div class="value">{{ $reg_no }} | {{ $nin }}</div>
            </div>
            <div class="detail-row">
                <div class="label">Gender / Zone</div>
                <div class="value">{{ $gender }} | {{ $zone }}</div>
            </div>
            <div class="detail-row">
                <div class="label">Coordinator</div>
                <div class="value" style="font-size: 5pt; color: {{ $accent_color }};">{{ $coordinator_name }} ({{ $coordinator_phone }})</div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <div class="qr-section">
        <div class="card-number">{{ $card_number }}</div>
        @if($qr_code)
            <img src="{{ $qr_code }}" class="qr-code" alt="QR">
        @else
            <div class="qr-code" style="background: #eee; line-height: 15mm; font-size: 4pt; color: #999;">NO QR</div>
        @endif
    </div>

    <div class="footer">
        <div class="valid-row">Issued: {{ $issue_date }} | Valid until: {{ $expiry_date }}</div>
    </div>
</div>
