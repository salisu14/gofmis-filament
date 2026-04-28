<?php

namespace App\Filament\Resources\IdCardTemplates\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdCardTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Standard Widow Card'),

                        Select::make('type')
                            ->options([
                                'widow' => 'Widow',
                                'orphan' => 'Orphan',
                            ])
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Visual Design')
                    ->schema([
                        ColorPicker::make('layout_config.primary_color')
                            ->label('Primary Color')
                            ->default('#1E90FF'),

                        ColorPicker::make('layout_config.secondary_color')
                            ->label('Secondary Color (Background)')
                            ->default('#F0F8FF'),

                        FileUpload::make('background_image_path')
                            ->label('Background Image')
                            ->image()
                            ->directory('card-backgrounds')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048),
                    ]),

                Section::make('Layout Configuration')
                    ->schema([
                        KeyValue::make('layout_config')
                            ->label('Additional Config')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->default([
                                'font_family' => 'Helvetica',
                                'photo_width_mm' => '16',
                                'photo_height_mm' => '20',
                                'qr_size_mm' => '16',
                                'header_height_mm' => '12',
                            ]),
                    ]),
            ]);
    }
}
