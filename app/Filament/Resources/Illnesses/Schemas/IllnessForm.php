<?php

namespace App\Filament\Resources\Illnesses\Schemas;

use App\Enums\IllnessCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IllnessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('category')
                    ->options(IllnessCategory::class),
            ]);
    }
}
