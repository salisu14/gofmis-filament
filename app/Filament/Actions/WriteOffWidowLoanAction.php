<?php

namespace App\Filament\Actions;

use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use App\Services\WidowLoanWriteOffService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class WriteOffWidowLoanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'writeOff';
    }

    protected function setUp(): void
    {
        parent::setUp();

        \App\Security\SensitiveActionConfirmation::apply(
            $this,
            \App\Enums\SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            'WRITE OFF LOAN',
            'loan_write_off'
        );

        $this->label('Write Off Loan')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (WidowLoan $record) => auth()->user()?->hasRole('super_admin')
                && $record->status === WidowLoanStatus::DISBURSED
                && (float) $record->outstanding_balance > 0
            )
            ->modalHeading('Write Off Loan (Waive Remaining Debt)')
            ->modalDescription(
                'Are you sure you want to write off the remaining balance of this loan? '.
                'Repayments already made will remain historically recorded, but the outstanding balance will be waived and set to zero. '.
                'This action cannot be undone.'
            )
            ->modalSubmitActionLabel('Write Off Balance')
            ->form([
                Textarea::make('write_off_reason')
                    ->label('Reason for Write-Off')
                    ->placeholder('Provide detailed explanation for this write-off (e.g. genuine hardship, illness, etc.)...')
                    ->required()
                    ->rows(3),

                Textarea::make('write_off_verification_notes')
                    ->label('Verification Notes')
                    ->placeholder('Notes on how the hardship was verified (e.g. physical visitation, coordinator report)...')
                    ->rows(2),

                FileUpload::make('write_off_document_path')
                    ->label('Supporting Verification Document (PDF/Image)')
                    ->disk('local')
                    ->directory('loan-write-offs')
                    ->visibility('private')
                    ->maxSize(2048)
                    ->helperText('Upload proof of verification or coordinator request (max 2MB)'),

                Toggle::make('reapplication_allowed')
                    ->label('Allow Reapplication in Future')
                    ->helperText('Check this if the widow should be eligible to apply for another loan in the future.')
                    ->default(false),
            ])
            ->action(function (WidowLoan $record, array $data): void {
                $service = new WidowLoanWriteOffService;

                try {
                    $service->writeOff(
                        $record,
                        auth()->user(),
                        $data['write_off_reason'],
                        $data['write_off_verification_notes'] ?? null,
                        (bool) ($data['reapplication_allowed'] ?? false),
                        $data['write_off_document_path'] ?? null
                    );

                    Notification::make()
                        ->title('Loan Written Off Successfully')
                        ->body($data['reapplication_allowed']
                            ? 'Loan written off; widow may reapply'
                            : 'Loan written off; widow remains restricted from new applications'
                        )
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error Writing Off Loan')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
