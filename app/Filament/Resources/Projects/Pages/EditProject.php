<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Services\ProjectService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),

            // Custom status actions
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check')
                ->color('success')
                ->visible(fn($record) => $record->status === ProjectStatus::PLANNING)
                ->requiresConfirmation()
                ->action(function (ProjectService $service) {
                    $service->approveProject($this->record);
                    Notification::make()->title('Project approved')->success()->send();
                    $this->redirect(static::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('start')
                ->label('Start Work')
                ->icon('heroicon-m-play')
                ->color('warning')
                ->visible(fn($record) => $record->status === ProjectStatus::APPROVED)
                ->requiresConfirmation()
                ->action(function (ProjectService $service) {
                    $service->startProject($this->record);
                    Notification::make()->title('Project started')->success()->send();
                    $this->redirect(static::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('complete')
                ->label('Mark Complete')
                ->icon('heroicon-m-flag')
                ->color('success')
                ->visible(fn($record) => $record->status === ProjectStatus::IN_PROGRESS)
                ->requiresConfirmation()
                ->action(function (ProjectService $service) {
                    $service->completeProject($this->record);
                    Notification::make()->title('Project completed')->success()->send();
                    $this->redirect(static::getUrl('edit', ['record' => $this->record]));
                }),

            Action::make('hold')
                ->label('Place on Hold')
                ->icon('heroicon-m-pause')
                ->color('danger')
                ->visible(fn($record) => $record->status === ProjectStatus::IN_PROGRESS)
                ->schema([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->required()
                        ->label('Reason for hold'),
                ])
                ->action(function (array $data, ProjectService $service) {
                    $service->holdProject($this->record, $data['reason']);
                    Notification::make()->title('Project placed on hold')->warning()->send();
                    $this->redirect(static::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }

//    protected function afterSave(): void
//    {
//        activity()
//            ->performedOn($this->record)
//            ->causedBy(auth()->user())
//            ->withProperties(['changes' => $this->record->getChanges()])
//            ->log('project_updated');
//    }

//    protected function getHeaderActions(): array
//    {
//        return [
//            ViewAction::make(),
//            DeleteAction::make(),
//            ForceDeleteAction::make(),
//            RestoreAction::make(),
//        ];
//    }
}
