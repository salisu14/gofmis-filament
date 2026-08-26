<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanHardshipStatus;
use App\Enums\WidowLoanRecoveryActivityType;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanStatus;
use App\Enums\WidowLoanWriteOffRecommendationStatus;
use App\Filament\Resources\WidowLoans\WidowLoanResource;
use App\Models\WidowLoan;
use App\Services\WidowLoanHardshipService;
use App\Services\WidowLoanRecoveryService;
use App\Services\WidowLoanRestructureService;
use App\Services\WidowLoanWriteOffRecommendationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWidowLoan extends ViewRecord
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (WidowLoan $record) => static::getResource()::canEdit($record)),

            // 1. Submit for approval & basic loan lifecycle
            \App\Filament\Actions\SubmitForApprovalAction::make(),
            \App\Filament\Actions\ApproveWidowLoanAction::make(),
            \App\Filament\Actions\RejectWidowLoanAction::make(),
            \App\Filament\Actions\DisburseWidowLoanAction::make(),
            \App\Filament\Actions\MarkLoanCollectedAction::make(),

            // 2. Report Hardship Case
            Action::make('reportHardship')
                ->label('Report Hardship')
                ->icon('heroicon-m-heart')
                ->color('warning')
                ->visible(function (WidowLoan $record): bool {
                    if ($record->status !== WidowLoanStatus::DISBURSED || $record->hardship_active) {
                        return false;
                    }

                    $user = auth()->user();
                    if (! $user) {
                        return false;
                    }

                    if ($user->hasRole('coordinator')) {
                        $coordinatedZoneId = $user->coordinatedZone?->id;
                        $widowZoneId = $record->widow?->deceased?->zone_id;

                        return $coordinatedZoneId && $coordinatedZoneId === $widowZoneId;
                    }

                    return $user->hasAnyRole(['admin', 'super_admin']);
                })
                ->form([
                    Select::make('reason_category')
                        ->label('Reason Category')
                        ->options([
                            'health_emergency' => 'Health / Medical Emergency',
                            'business_loss' => 'Business / Income Loss',
                            'family_bereavement' => 'Family Bereavement',
                            'natural_disaster' => 'Natural Disaster / Fire',
                            'other' => 'Other Hardship',
                        ])
                        ->required(),

                    Textarea::make('reason_details')
                        ->label('Hardship Details')
                        ->placeholder('Explain the widow\'s circumstances in detail...')
                        ->required()
                        ->rows(3),

                    FileUpload::make('supporting_document_path')
                        ->label('Supporting Document (Optional)')
                        ->disk('local')
                        ->directory('hardship-evidence')
                        ->visibility('private')
                        ->maxSize(2048),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        app(WidowLoanHardshipService::class)->reportHardshipCase(
                            loanId: $record->id,
                            reportedById: auth()->id(),
                            reasonCategory: $data['reason_category'],
                            reasonDetails: $data['reason_details'],
                            supportingDocumentPath: $data['supporting_document_path'] ?? null
                        );

                        Notification::make()
                            ->success()
                            ->title('Hardship Case Reported')
                            ->body('The hardship case has been reported and sent for verification.')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Hardship Reporting Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 3. Verify Hardship (Admin / Super Admin)
            Action::make('verifyHardship')
                ->label('Verify Hardship')
                ->icon('heroicon-m-check-badge')
                ->color('info')
                ->visible(fn (WidowLoan $record): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin']) &&
                    $record->hardshipCases()->where('status', WidowLoanHardshipStatus::PENDING)->exists()
                )
                ->form([
                    Textarea::make('verification_notes')
                        ->label('Verification Notes')
                        ->placeholder('Enter details of verification conducted...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $pendingCase = $record->hardshipCases()->where('status', WidowLoanHardshipStatus::PENDING)->first();
                        if ($pendingCase) {
                            app(WidowLoanHardshipService::class)->verifyHardshipCase($pendingCase->id, auth()->id(), $data['verification_notes']);

                            Notification::make()
                                ->success()
                                ->title('Hardship Verified')
                                ->body('The hardship case is verified and awaiting approval.')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Verification Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 4. Approve Hardship & Grant Relief (Super Admin)
            Action::make('approveHardship')
                ->label('Approve Hardship & Relief')
                ->icon('heroicon-m-shield-check')
                ->color('success')
                ->visible(fn (WidowLoan $record): bool => auth()->user()?->hasRole('super_admin') &&
                    $record->hardshipCases()->where('status', WidowLoanHardshipStatus::VERIFIED)->exists()
                )
                ->form([
                    TextInput::make('recommended_action')
                        ->label('Recommended Action')
                        ->default('Grant temporary payment relief period')
                        ->required(),

                    DatePicker::make('starts_at')
                        ->label('Relief Start Date')
                        ->default(now())
                        ->required()
                        ->native(false),

                    DatePicker::make('ends_at')
                        ->label('Relief End Date')
                        ->default(now()->addMonths(1))
                        ->required()
                        ->native(false),

                    TextInput::make('relief_reason')
                        ->label('Relief Reason')
                        ->default('Hardship relief period granted')
                        ->required(),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $verifiedCase = $record->hardshipCases()->where('status', WidowLoanHardshipStatus::VERIFIED)->first();
                        if ($verifiedCase) {
                            $service = app(WidowLoanHardshipService::class);
                            $service->approveHardshipCase($verifiedCase->id, auth()->id(), $data['recommended_action']);
                            $service->createReliefPeriod($record->id, $verifiedCase->id, $data['starts_at'], $data['ends_at'], $data['relief_reason'], auth()->id());

                            Notification::make()
                                ->success()
                                ->title('Hardship Relief Approved')
                                ->body('Relief period active. Loan default classification is paused during relief.')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Approval Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 5. Reject Hardship Case (Admin / Super Admin)
            Action::make('rejectHardship')
                ->label('Reject Hardship')
                ->icon('heroicon-m-x-mark')
                ->color('danger')
                ->visible(fn (WidowLoan $record): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin']) &&
                    $record->hardshipCases()->whereIn('status', [WidowLoanHardshipStatus::PENDING, WidowLoanHardshipStatus::VERIFIED])->exists()
                )
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(2),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $case = $record->hardshipCases()->whereIn('status', [WidowLoanHardshipStatus::PENDING, WidowLoanHardshipStatus::VERIFIED])->first();
                        if ($case) {
                            app(WidowLoanHardshipService::class)->rejectHardshipCase($case->id, auth()->id(), $data['rejection_reason']);

                            Notification::make()
                                ->warning()
                                ->title('Hardship Case Rejected')
                                ->body('The hardship request has been rejected.')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Rejection Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 6. Record Recovery Activity
            Action::make('recordRecoveryActivity')
                ->label('Record Recovery Activity')
                ->icon('heroicon-m-clipboard-document-list')
                ->color('warning')
                ->visible(fn (WidowLoan $record): bool => $record->recoveryCases()
                    ->whereNotIn('status', [WidowLoanRecoveryStatus::CLOSED, WidowLoanRecoveryStatus::RESOLVED])
                    ->exists()
                )
                ->form([
                    Select::make('activity_type')
                        ->label('Activity Type')
                        ->options(WidowLoanRecoveryActivityType::class)
                        ->required(),

                    Select::make('contact_method')
                        ->label('Contact Method')
                        ->options([
                            'phone' => 'Phone Call',
                            'home_visit' => 'Home Visit',
                            'office_visit' => 'Office Visit',
                            'letter' => 'Reminder Letter',
                            'other' => 'Other',
                        ])
                        ->default('phone')
                        ->required(),

                    Textarea::make('notes')
                        ->label('Activity Notes')
                        ->required()
                        ->rows(3),

                    DatePicker::make('next_follow_up_at')
                        ->label('Next Follow-Up Date')
                        ->native(false),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $case = $record->recoveryCases()
                            ->whereNotIn('status', [WidowLoanRecoveryStatus::CLOSED, WidowLoanRecoveryStatus::RESOLVED])
                            ->first();

                        if ($case) {
                            $activityType = $data['activity_type'] instanceof WidowLoanRecoveryActivityType
                                ? $data['activity_type']
                                : WidowLoanRecoveryActivityType::from($data['activity_type']);

                            app(WidowLoanRecoveryService::class)->createRecoveryActivity(
                                caseId: $case->id,
                                type: $activityType,
                                notes: $data['notes'],
                                contactMethod: $data['contact_method'],
                                nextFollowUpAt: $data['next_follow_up_at'] ?? null,
                                performedBy: auth()->id()
                            );

                            Notification::make()
                                ->success()
                                ->title('Recovery Activity Recorded')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to Record Activity')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 7. Record Promise to Pay
            Action::make('recordPromiseToPay')
                ->label('Record Promise to Pay')
                ->icon('heroicon-m-currency-dollar')
                ->color('primary')
                ->visible(fn (WidowLoan $record): bool => $record->recoveryCases()
                    ->whereNotIn('status', [WidowLoanRecoveryStatus::CLOSED, WidowLoanRecoveryStatus::RESOLVED])
                    ->exists()
                )
                ->form([
                    TextInput::make('promise_amount')
                        ->label('Promised Amount')
                        ->numeric()
                        ->prefix('₦')
                        ->required(),

                    DatePicker::make('promise_date')
                        ->label('Promised Date')
                        ->required()
                        ->native(false),

                    Select::make('contact_method')
                        ->label('Contact Method')
                        ->options([
                            'phone' => 'Phone Call',
                            'home_visit' => 'Home Visit',
                            'office_visit' => 'Office Visit',
                            'letter' => 'Reminder Letter',
                            'other' => 'Other',
                        ])
                        ->default('phone')
                        ->required(),

                    Textarea::make('notes')
                        ->label('Notes / Commitment Details')
                        ->required()
                        ->rows(2),

                    DatePicker::make('next_follow_up_at')
                        ->label('Follow-Up Date')
                        ->native(false),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $case = $record->recoveryCases()
                            ->whereNotIn('status', [WidowLoanRecoveryStatus::CLOSED, WidowLoanRecoveryStatus::RESOLVED])
                            ->first();

                        if ($case) {
                            app(WidowLoanRecoveryService::class)->createRecoveryActivity(
                                caseId: $case->id,
                                type: WidowLoanRecoveryActivityType::PROMISE_TO_PAY,
                                notes: $data['notes'],
                                contactMethod: $data['contact_method'],
                                promiseAmount: (float) $data['promise_amount'],
                                promiseDate: $data['promise_date'],
                                nextFollowUpAt: $data['next_follow_up_at'] ?? null,
                                performedBy: auth()->id()
                            );

                            Notification::make()
                                ->success()
                                ->title('Promise to Pay Registered')
                                ->body('The commitment has been recorded and case status updated.')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Action Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 8. Recommend Write-Off
            Action::make('recommendWriteOff')
                ->label('Recommend Write-Off')
                ->icon('heroicon-m-document-minus')
                ->color('danger')
                ->visible(fn (WidowLoan $record): bool => $record->status === WidowLoanStatus::DISBURSED &&
                    (float) $record->outstanding_balance > 0 &&
                    auth()->user()?->hasAnyRole(['coordinator', 'admin']) &&
                    ! $record->writeOffRecommendations()
                        ->whereIn('status', [
                            WidowLoanWriteOffRecommendationStatus::PENDING,
                            WidowLoanWriteOffRecommendationStatus::ENDORSED,
                        ])
                        ->exists()
                )
                ->form([
                    TextInput::make('recommended_amount')
                        ->label('Recommended Write-Off Amount')
                        ->numeric()
                        ->prefix('₦')
                        ->default(fn (WidowLoan $record) => $record->outstanding_balance)
                        ->required(),

                    Textarea::make('reason')
                        ->label('Reason for Write-Off Recommendation')
                        ->placeholder('Provide detailed justification (e.g. death, irreversible hardship)...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        app(WidowLoanWriteOffRecommendationService::class)->recommendWriteOff(
                            loanId: $record->id,
                            hardshipCaseId: $record->hardshipCases()->first()?->id,
                            recoveryCaseId: $record->recoveryCases()->first()?->id,
                            amount: (float) $data['recommended_amount'],
                            reason: $data['reason'],
                            recommendedBy: auth()->id()
                        );

                        Notification::make()
                            ->success()
                            ->title('Write-Off Recommended')
                            ->body('The recommendation has been submitted for administrative review.')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Recommendation Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 9. Propose Restructure
            Action::make('proposeRestructure')
                ->label('Propose Restructure')
                ->icon('heroicon-m-adjustments-horizontal')
                ->color('info')
                ->visible(fn (WidowLoan $record): bool => $record->status === WidowLoanStatus::DISBURSED &&
                    (float) $record->outstanding_balance > 0 &&
                    auth()->user()?->hasAnyRole(['coordinator', 'admin']) &&
                    ! $record->restructures()->where('status', \App\Enums\WidowLoanRestructureStatus::PENDING_APPROVAL)->exists()
                )
                ->form([
                    TextInput::make('new_duration_months')
                        ->label('New Term (Months)')
                        ->numeric()
                        ->required(),

                    Select::make('new_repayment_frequency')
                        ->label('New Repayment Frequency')
                        ->options(LoanRepaymentFrequency::class)
                        ->required(),

                    TextInput::make('new_installment_amount')
                        ->label('New Installment Amount')
                        ->numeric()
                        ->prefix('₦')
                        ->required(),

                    DatePicker::make('effective_date')
                        ->label('Effective Start Date')
                        ->default(now())
                        ->required()
                        ->native(false),

                    Textarea::make('reason')
                        ->label('Restructure Reason')
                        ->required()
                        ->rows(2),
                ])
                ->action(function (WidowLoan $record, array $data): void {
                    try {
                        $frequency = $data['new_repayment_frequency'] instanceof LoanRepaymentFrequency
                            ? $data['new_repayment_frequency']
                            : LoanRepaymentFrequency::from($data['new_repayment_frequency']);

                        app(WidowLoanRestructureService::class)->proposeRestructure(
                            loanId: $record->id,
                            hardshipCaseId: $record->hardshipCases()->first()?->id,
                            newDurationMonths: (int) $data['new_duration_months'],
                            newFrequency: $frequency,
                            newInstallmentAmount: (float) $data['new_installment_amount'],
                            effectiveDate: $data['effective_date'],
                            reason: $data['reason'],
                            requestedBy: auth()->id()
                        );

                        Notification::make()
                            ->success()
                            ->title('Restructure Proposal Submitted')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Restructure Proposal Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 10. Approve Restructure (Super Admin)
            Action::make('approveRestructure')
                ->label('Approve Restructure')
                ->icon('heroicon-m-check')
                ->color('success')
                ->visible(fn (WidowLoan $record): bool => auth()->user()?->hasRole('super_admin') &&
                    $record->restructures()->where('status', \App\Enums\WidowLoanRestructureStatus::PENDING_APPROVAL)->exists()
                )
                ->requiresConfirmation()
                ->action(function (WidowLoan $record): void {
                    try {
                        $pending = $record->restructures()->where('status', \App\Enums\WidowLoanRestructureStatus::PENDING_APPROVAL)->first();
                        if ($pending) {
                            app(WidowLoanRestructureService::class)->approveAndApply($pending->id, auth()->id());

                            Notification::make()
                                ->success()
                                ->title('Restructure Approved & Applied')
                                ->body('New schedule installments generated and historical repayments preserved.')
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Restructure Approval Failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            // 11. Final Write-Off (Super Admin with MFA)
            \App\Filament\Actions\WriteOffWidowLoanAction::make(),

            Action::make('downloadStatement')
                ->label('Download Statement')
                ->icon('heroicon-m-document-text')
                ->color('info')
                ->url(fn ($record) => route('loans.statement.download', $record))
                ->openUrlInNewTab()
                ->visible(fn ($record) => $record->repayments()->exists()),

        ];
    }
}
