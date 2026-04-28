<?php

// app/Filament/Resources/Widows/Actions/GenerateIdCardAction.php


namespace App\Filament\Resources\Widows\Actions;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\Widow;
use App\Services\IdCardGenerationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class GenerateIdCardAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generate_id_card';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Generate ID Card')
            ->icon('heroicon-o-identification')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Generate ID Card')
            ->modalDescription('This will create a new ID card for this widow.')
            ->modalSubmitActionLabel('Generate')
            ->visible(fn(Widow $record): bool => $record->is_eligible &&
                !$record->idCards()->where('status', 'active')->exists()
            )
            ->action(function (Widow $record) {
                try {
                    $service = app(IdCardGenerationService::class);
                    $card = $service->generateCard($record);

                    Notification::make()
                        ->title('ID Card Generated')
                        ->body("Card number: {$card->card_number}")
                        ->success()
                        ->send();

                    // Redirect to card view
                    $this->redirect(
                        IdCardResource::getUrl('view', ['record' => $card])
                    );

                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Generation Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
