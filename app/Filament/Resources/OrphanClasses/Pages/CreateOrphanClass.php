<?php

namespace App\Filament\Resources\OrphanClasses\Pages;

use App\Filament\Resources\OrphanClasses\OrphanClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrphanClass extends CreateRecord
{
    protected static string $resource = OrphanClassResource::class;
}
