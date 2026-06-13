<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\IdCard;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIdCard extends ViewRecord
{
    protected static string $resource = IdCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('preview')
                ->label('Preview PDF')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn(IdCard $record): string => route('id-cards.preview', ['card' => $record]))
                ->openUrlInNewTab(),

            Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn(IdCard $record) => route(
                    'id-cards.download',
                    ['idCard' => $record]
                ))
                ->openUrlInNewTab(),
        ];
    }
}
