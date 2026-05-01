<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $relatedResource = ProjectResource::class;

    protected static ?string $title = 'Media Gallery';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->options([
                        'photo' => 'Photo',
                        'video' => 'Video',
                        'document' => 'Document',
                    ])
                    ->required(),

                TextInput::make('title')
                    ->nullable(),

                FileUpload::make('file_path')
                    ->label('File')
                    ->disk('public') // ✅ important
                    ->directory('project-media')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->required()
                    ->imagePreviewHeight('250')
                    ->panelAspectRatio('2:1')
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Preview')
                    ->square()
                    ->imageSize(80)
                    ->defaultImageUrl(fn($record) => $record->type === 'video'
                        ? asset('images/video-icon.png')
                        : asset('images/document-icon.png')
                    ),
                TextColumn::make('title')
                    ->placeholder('Untitled'),
                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'photo',
                        'warning' => 'video',
                        'info' => 'document',
                    ]),
                TextColumn::make('uploader.name')
                    ->label('Uploaded By'),
                TextColumn::make('created_at')
                    ->date('M d, Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Media')
                    ->mutateDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth()->id(); // ✅ FIX
                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn($record) => Storage::url($record->file_path))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ]);
    }
}
