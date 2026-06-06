<?php

use App\Models\IdCard;
use App\Models\Widow;
use App\Services\IdCardPDFService;
use Illuminate\Support\Facades\Storage;

it('uses public storage URLs for browser card previews', function () {
    Storage::fake('public');
    Storage::disk('public')->put('widow-photos/hannatu.jpg', 'image-bytes');

    $widow = new Widow([
        'full_name' => 'Hannatu Musa',
        'reg_no' => 'GOF-W-001',
        'picture_url' => 'widow-photos/hannatu.jpg',
        'is_eligible' => true,
    ]);

    $idCard = new IdCard([
        'cardable_type' => Widow::class,
        'card_number' => 'GOF-W-2026-0001',
        'issued_at' => now(),
        'expires_at' => now()->addYear(),
    ]);
    $idCard->setRelation('cardable', $widow);

    $data = app(IdCardPDFService::class)->prepareCardDataForBrowser($idCard);

    expect($data['photo_url'])->toContain('/storage/widow-photos/hannatu.jpg');
});

it('trims trailing blank pages from single card PDFs', function () {
    $widow = new Widow([
        'full_name' => 'Hannatu Musa',
        'reg_no' => 'GOF-W-001',
        'nin' => '12345678901',
        'picture_url' => null,
        'is_eligible' => true,
    ]);

    $idCard = new IdCard([
        'cardable_type' => Widow::class,
        'card_number' => 'GOF-W-2026-0001',
        'issued_at' => now(),
        'expires_at' => now()->addYear(),
    ]);
    $idCard->setRelation('cardable', $widow);

    $pdf = app(IdCardPDFService::class)->generateSingle($idCard);

    expect($pdf->getDomPDF()->getCanvas()->get_page_count())->toBe(1)
        ->and($pdf->output())->toStartWith('%PDF');
});
