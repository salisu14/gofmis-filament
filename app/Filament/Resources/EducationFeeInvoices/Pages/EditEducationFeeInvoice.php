<?php

namespace App\Filament\Resources\EducationFeeInvoices\Pages;

use App\Filament\Resources\EducationFeeInvoices\EducationFeeInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEducationFeeInvoice extends EditRecord
{
    protected static string $resource = EducationFeeInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
