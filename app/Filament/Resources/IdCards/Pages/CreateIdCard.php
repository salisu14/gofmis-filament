<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Jobs\GenerateIdCardPdfJob;
use App\Models\IdCard;
use App\Services\QRCodeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateIdCard extends CreateRecord
{
    protected static string $resource = IdCardResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            if (blank($data['card_number'] ?? null)) {
                $data['card_number'] = Str::upper(Str::random(8));
            }

            // Satisfy DB NOT NULL constraint before generating the actual QR image path.
            $data['qr_code_path'] = $data['qr_code_path'] ?? '';

            /** @var IdCard $idCard */
            $idCard = static::getModel()::create($data);

            $qrPath = app(QRCodeService::class)->generateForCard($idCard);
            $idCard->update(['qr_code_path' => $qrPath]);

            GenerateIdCardPdfJob::dispatch($idCard);

            return $idCard->fresh();
        });
    }
}
