<?php

namespace App\Filament\Resources\IdCards\Schemas;

use App\Models\Orphan;
use App\Models\Widow;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Card Assignment')
                    ->schema([
                        MorphToSelect::make('cardable')
                            ->types([
                                Type::make(Widow::class)
                                    ->titleAttribute('full_name')
                                    ->modifyOptionsQueryUsing(fn($query) => $query->where('is_eligible', true)),
                                Type::make(Orphan::class)
                                    ->titleAttribute('full_name')
                                    ->modifyOptionsQueryUsing(fn($query) => $query->where('is_eligible', true)),
                            ])
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('template_id')
                            ->relationship('template', 'name')
                            ->required(),
                    ]),

                Section::make('Card Details')
                    ->schema([
                        TextInput::make('card_number')
                            ->unique(ignoreRecord: true)
                            ->placeholder('Auto-generated if left empty')
                            ->disabledOn('edit'),

                        DateTimePicker::make('issued_at')
                            ->required()
                            ->default(now()),

                        DateTimePicker::make('expires_at')
                            ->default(now()->addYears(2)),

                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'revoked' => 'Revoked',
                                'expired' => 'Expired',
                            ])
                            ->required()
                            ->default('draft'),
                    ]),
            ]);
    }
}
