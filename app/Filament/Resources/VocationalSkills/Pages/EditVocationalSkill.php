<?php

namespace App\Filament\Resources\VocationalSkills\Pages;

use App\Filament\Resources\VocationalSkills\VocationalSkillResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVocationalSkill extends EditRecord
{
    protected static string $resource = VocationalSkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
