<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Jobs\GenerateIdCardPdfJob;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Widow;
use App\Services\QRCodeService;
use App\Services\IdCardGenerationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateIdCard extends CreateRecord
{
    protected static string $resource = IdCardResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $type = ($data['cardable_type'] ?? null) === Widow::class ? 'widow' : 'orphan';
            $template = isset($data['template_id'])
                ? IdCardTemplate::find($data['template_id'])
                : IdCardTemplate::defaultForType($type);

            if (! $template || $template->type !== $type || ! $template->is_active) {
                throw ValidationException::withMessages([
                    'template_id' => 'Select an active template that matches this beneficiary type.',
                ]);
            }

            if (blank($data['card_number'] ?? null)) {
                $data['card_number'] = app(IdCardGenerationService::class)->generateCardNumber($type);
            }

            $data['template_id'] = $template->id;

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
