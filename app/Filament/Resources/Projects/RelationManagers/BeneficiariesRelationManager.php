<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BeneficiariesRelationManager extends RelationManager
{
    protected static string $relationship = 'beneficiaries';

    protected static ?string $relatedResource = ProjectResource::class;

    protected static ?int $sort = 3;

    protected static ?string $label = 'Beneficiary';

    protected static ?string $title = 'Beneficiaries';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('beneficiary_type')
                    ->options([
                        Orphan::class => 'Orphan',
                        Widow::class => 'Widow',
                    ])
                    ->required()
                    ->live(),

                Select::make('beneficiary_id')
                    ->label('Beneficiary')
                    ->options(function (Get $get) {
                        $type = $get('beneficiary_type');
                        if (!$type) return [];
                        return $type::pluck('full_name', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('role')
                    ->options([
                        'primary' => 'Primary Beneficiary',
                        'secondary' => 'Secondary',
                        'dependent' => 'Dependent',
                    ])
                    ->default('primary')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('beneficiary.full_name')
                    ->label('Name'),
                TextColumn::make('beneficiary_type')
                    ->badge()
                    ->formatStateUsing(fn($state) => class_basename($state)),
                TextColumn::make('role')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Beneficiary'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
