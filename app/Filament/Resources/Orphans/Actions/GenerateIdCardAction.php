<?php

// app/Filament/Resources/Orphans/Actions/GenerateIdCardAction.php

namespace App\Filament\Resources\Orphans\Actions;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\Orphan;
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
            ->modalDescription('This will create a new ID card for this orphan.')
            ->modalSubmitActionLabel('Generate')
            ->visible(fn(Orphan $record): bool => $record->is_eligible &&
                !$record->idCards()->where('status', 'active')->exists()
            )
            ->action(function (Orphan $record) {
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
