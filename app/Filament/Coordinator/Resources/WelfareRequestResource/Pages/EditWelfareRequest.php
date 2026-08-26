<?php

// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/EditWelfareRequest.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Enums\BeneficiaryStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWelfareRequest extends EditRecord
{
    protected static string $resource = WelfareRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->status !== BeneficiaryStatus::PENDING) {
            abort(403, 'Only pending welfare nominations can be edited.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['admin', 'super_admin']) && $this->getRecord()->status === BeneficiaryStatus::PENDING),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['welfare_package_id']) && is_array($data['welfare_package_id'])) {
            $data['welfare_package_id'] = reset($data['welfare_package_id']);
        }

        if (isset($data['deceased_id']) && is_array($data['deceased_id'])) {
            $data['deceased_id'] = reset($data['deceased_id']);
        }

        $packageId = $data['welfare_package_id'] ?? $this->getRecord()->welfare_package_id;
        $deceasedId = $data['deceased_id'] ?? $this->getRecord()->deceased_id;

        if ($packageId && $deceasedId) {
            $exists = \App\Models\WelfareBeneficiary::where('welfare_package_id', $packageId)
                ->where('deceased_id', $deceasedId)
                ->where('id', '!=', $this->getRecord()->id)
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deceased_id' => 'This family already has a welfare nomination/allocation for the selected package.',
                ]);
            }
        }

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique_package_deceased') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deceased_id' => 'This family already has a welfare nomination/allocation for the selected package.',
                ]);
            }

            throw $e;
        }
    }

    protected function beforeSave(): void
    {
        if ($this->record->status !== BeneficiaryStatus::PENDING) {
            $this->halt();

            Notification::make()
                ->title('Cannot Edit')
                ->body('Only pending nominations can be edited.')
                ->danger()
                ->send();
        }
    }
}
