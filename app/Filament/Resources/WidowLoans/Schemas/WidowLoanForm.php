<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Widow;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Application')
                    ->description('Identify the borrower and the primary terms of the loan.')
                    ->icon('heroicon-m-document-currency-dollar')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('widow_id')
                                ->label('Widow (Borrower)')
                                ->relationship('widow', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabledOn('edit')
                                ->live()
                                ->hint(function ($state) {
                                    if (! $state) {
                                        return null;
                                    }
                                    $widow = Widow::find($state);
                                    if (! $widow) {
                                        return null;
                                    }
                                    if ($widow->is_married) {
                                        return '⚠️ Remarried (Ineligible)';
                                    }
                                    if ($widow->widowLoans()->where('status', WidowLoanStatus::WRITTEN_OFF)->where('reapplication_allowed', false)->exists()) {
                                        return '❌ Denied reapplication after write-off';
                                    }

                                    return $widow->canApplyForLoan() ? '✅ Eligible' : '❌ Active Loan Exists';
                                }),

                            \Filament\Forms\Components\Section::make('Previous Loan Write-Off')
                                ->description('Important notice regarding the borrower\'s loan history.')
                                ->icon('heroicon-m-exclamation-triangle')
                                ->visible(function ($get) {
                                    $widowId = $get('widow_id');
                                    if (! $widowId) {
                                        return false;
                                    }
                                    $widow = Widow::find($widowId);

                                    return $widow && $widow->widowLoans()->where('status', WidowLoanStatus::WRITTEN_OFF)->exists();
                                })
                                ->schema([
                                    \Filament\Forms\Components\Placeholder::make('write_off_details')
                                        ->label('')
                                        ->content(function ($get) {
                                            $widowId = $get('widow_id');
                                            if (! $widowId) {
                                                return '';
                                            }
                                            $widow = Widow::find($widowId);
                                            if (! $widow) {
                                                return '';
                                            }
                                            $writtenOffLoan = $widow->widowLoans()
                                                ->where('status', WidowLoanStatus::WRITTEN_OFF)
                                                ->first();
                                            if (! $writtenOffLoan) {
                                                return '';
                                            }

                                            $writeOff = $writtenOffLoan->writeOff;
                                            $amount = number_format($writtenOffLoan->amount_written_off ?? $writeOff?->amount_written_off ?? 0, 2);
                                            $date = $writtenOffLoan->written_off_at?->format('M d, Y') ?? $writeOff?->authorized_at?->format('M d, Y') ?? 'N/A';
                                            $reason = $writeOff?->write_off_reason ?? 'N/A';
                                            $authorizedBy = $writtenOffLoan->writtenOffBy?->name ?? $writeOff?->authorizedBy?->name ?? 'N/A';

                                            return new \Illuminate\Support\HtmlString("
                                                <div class='p-4 bg-danger-50 border border-danger-200 rounded-lg text-danger-900 dark:bg-danger-950 dark:border-danger-800 dark:text-danger-100'>
                                                    <h4 class='font-bold text-lg mb-2 text-danger-800 dark:text-danger-200'>⚠️ Previous Loan Write-Off Notice</h4>
                                                    <p class='mb-1'><strong>Loan ID:</strong> {$writtenOffLoan->id}</p>
                                                    <p class='mb-1'><strong>Amount Written Off:</strong> ₦{$amount}</p>
                                                    <p class='mb-1'><strong>Date:</strong> {$date}</p>
                                                    <p class='mb-1'><strong>Reason:</strong> {$reason}</p>
                                                    <p class='mb-1'><strong>Authorized By:</strong> {$authorizedBy}</p>
                                                </div>
                                            ");
                                        }),
                                ])
                                ->columnSpanFull(),

                            TextInput::make('purpose')
                                ->label('Loan Purpose')
                                ->placeholder('e.g., Small business expansion')
                                ->required()
                                ->maxLength(255),

                            Select::make('bank_account_id')
                                ->label('Foundation Disbursing Account')
                                ->helperText('Use a child account dedicated to widow loan disbursements.')
                                ->relationship(
                                    name: 'bankAccount',
                                    titleAttribute: 'account_name',
                                    modifyQueryUsing: fn ($query) => $query->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT)
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_name} ({$record->account_number})")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('disbursement_bank_id')
                                ->label('Widow\'s Receiving Account')
                                ->helperText('The bank account the widow receives funds into.')
                                ->relationship('disbursementBank', 'account_name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_name} ({$record->account_number})")
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            Select::make('repayment_bank_id')
                                ->label('Foundation Repayment Account')
                                ->helperText('Use a child account dedicated to widow loan repayments.')
                                ->options(fn () => BankAccount::query()
                                    ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
                                    ->orderBy('account_name')
                                    ->pluck('account_name', 'id')
                                    ->toArray()
                                )
                                ->getOptionLabelUsing(
                                    fn ($value) => BankAccount::find($value)?->display_name
                                )
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('principal_amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $set) => $set('total_payable', (float) $state)),

                            Select::make('repayment_frequency')
                                ->label('Repayment Frequency')
                                ->options(LoanRepaymentFrequency::class)
                                ->required()
                                ->default(LoanRepaymentFrequency::WEEKLY)
                                ->native(false)
                                ->live(),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('duration_months')
                                ->label('Duration (Months)')
                                ->helperText('Number of months for the loan term.')
                                ->numeric()
                                ->default(12)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $get, $set) {
                                    $principal = (float) $get('total_payable');
                                    $duration = (int) $state;
                                    $freq = $get('repayment_frequency');
                                    if ($principal > 0 && $duration > 0) {
                                        $intervals = ($freq === LoanRepaymentFrequency::WEEKLY->value || $freq === 'weekly')
                                            ? $duration * 4
                                            : $duration;
                                        $set('installment_amount', round($principal / $intervals, 2));
                                    }
                                }),

                            TextInput::make('total_payable')
                                ->label('Total Payable')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly(),

                            TextInput::make('installment_amount')
                                ->label('Per Installment')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly()
                                ->helperText('Auto-calculated from frequency & duration.'),
                        ]),
                    ]),

                Section::make('Status & Documentation')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(3)->schema([
                            // Status is read-only — it is managed by the approval and service workflow.
                            Select::make('status')
                                ->options(WidowLoanStatus::class)
                                ->required()
                                ->default(WidowLoanStatus::DRAFT->value)
                                ->native(false)
                                ->disabled()
                                ->dehydrated(),

                            // disbursed_at is set automatically by DisburseWidowLoanAction — not manually editable.
                            DateTimePicker::make('disbursed_at')
                                ->label('Disbursement Date')
                                ->native(false)
                                ->readOnly()
                                ->helperText('Set automatically when the Disburse action is triggered.'),

                            // collected_at is set automatically by MarkLoanCollectedAction.
                            DateTimePicker::make('collected_at')
                                ->label('Collection Confirmed At')
                                ->native(false)
                                ->readOnly()
                                ->helperText('Set automatically when the widow confirms collection.'),
                        ]),

                        FileUpload::make('loan_agreement_url')
                            ->label('Signed Loan Agreement')
                            ->directory('loan-documents')
                            ->disk('public')
                            ->acceptedFileTypes(['application/pdf']),

                        Textarea::make('reject_reason')
                            ->label('Rejection Reason')
                            ->visible(fn ($get) => $get('status') === WidowLoanStatus::REJECTED->value)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
