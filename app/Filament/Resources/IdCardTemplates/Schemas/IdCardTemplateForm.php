<?php

namespace App\Filament\Resources\IdCardTemplates\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                            ->helperText('Only active templates are available when generating cards.')
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
                        Grid::make(4)->schema([
                            TextInput::make('layout_config.font_family')
                                ->label('Font Family')
                                ->default('Helvetica')
                                ->required(),

                            TextInput::make('layout_config.photo_width_mm')
                                ->label('Photo Width (mm)')
                                ->numeric()
                                ->default(16)
                                ->required(),

                            TextInput::make('layout_config.photo_height_mm')
                                ->label('Photo Height (mm)')
                                ->numeric()
                                ->default(20)
                                ->required(),

                            TextInput::make('layout_config.qr_size_mm')
                                ->label('QR Size (mm)')
                                ->numeric()
                                ->default(13)
                                ->required(),

                            TextInput::make('layout_config.header_height_mm')
                                ->label('Header Height (mm)')
                                ->numeric()
                                ->default(13.5)
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
