<?php

namespace App\Filament\Resources\VocationalSkills\Pages;

use App\Filament\Resources\VocationalSkills\VocationalSkillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVocationalSkills extends ListRecords
{
    protected static string $resource = VocationalSkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
