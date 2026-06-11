<?php

namespace App\Filament\Resources\Verifications\Pages;

use App\Filament\Resources\Verifications\EducationVerificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditEducationVerification extends EditRecord
{
    protected static string $resource = EducationVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasRole(['admin', 'super_admin'])),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Verification updated successfully';
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (($data['status'] ?? null) === 'approved' && ($data['verification_status'] ?? null) !== 'verified') {
            throw ValidationException::withMessages([
                'verification_status' => 'Education requests must be verified before approval.',
            ]);
        }

        return DB::transaction(function () use ($record, $data): Model {
            $targetStatus = $data['status'] ?? $record->status;
            $targetVerificationStatus = $data['verification_status'] ?? $record->verification_status;
            $wasVerified = $record->verification_status === 'verified';

            $record->update([
                'verification_status' => $targetVerificationStatus,
                'verification_notes' => $data['verification_notes'] ?? $record->verification_notes,
                'verification_documents' => $data['verification_documents'] ?? $record->verification_documents,
                'verified_by' => $targetVerificationStatus === 'verified' ? auth()->id() : $record->verified_by,
                'verified_at' => $targetVerificationStatus === 'verified' ? now() : $record->verified_at,
                'reviewed_by' => $record->reviewed_by ?? auth()->id(),
                'reviewed_at' => $record->reviewed_at ?? now(),
            ]);

            if ($targetStatus === 'under_review' && $record->status === 'pending') {
                $record->startReview(auth()->id());
            }

            if ($targetVerificationStatus === 'verified' && ! $wasVerified) {
                $record->markVerified(auth()->id(), $data['verification_notes'] ?? null);
            }

            if ($targetStatus === 'approved') {
                $record->approveRequest(auth()->id());
            }

            if ($targetStatus === 'rejected') {
                $record->rejectRequest($data['verification_notes'] ?? $record->rejection_reason, auth()->id());
            }

            return $record->refresh();
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
