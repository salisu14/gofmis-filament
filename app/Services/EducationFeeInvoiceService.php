<?php

namespace App\Services;

use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\EducationFeeInvoice;
use App\Models\EducationFeePayment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EducationFeeInvoiceService
{
    public const PAYING_ACCOUNT_USAGES = [
        BankAccount::USAGE_EDUCATION,
        BankAccount::USAGE_EDUCATION_BENEVOLENT,
    ];

    public function recordPayment(EducationFeeInvoice $invoice, array $data): EducationFeePayment
    {
        return DB::transaction(function () use ($invoice, $data): EducationFeePayment {
            $invoice = EducationFeeInvoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->isFinalized()) {
                throw ValidationException::withMessages([
                    'amount' => 'Payments cannot be recorded against a finalized invoice.',
                ]);
            }

            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than zero.',
                ]);
            }

            if ($amount > (float) $invoice->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment is higher than the outstanding balance.',
                ]);
            }

            $bank = BankAccount::query()
                ->whereKey($data['bank_account_id'] ?? null)
                ->lockForUpdate()
                ->firstOrFail();

            $bank->ensureDedicatedTo(self::PAYING_ACCOUNT_USAGES, 'education fee or benevolent sponsorship payments');

            try {
                $bank->debit($amount);
            } catch (InsufficientBankBalanceException $exception) {
                throw ValidationException::withMessages([
                    'bank_account_id' => $exception->getMessage(),
                ]);
            }

            /** @var EducationFeePayment $payment */
            $payment = $invoice->payments()->create([
                'bank_account_id' => $bank->id,
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? now(),
                'payment_method' => $data['payment_method'] ?? 'transfer',
                'reference' => $data['reference'] ?? null,
            ]);

            Transaction::create([
                'bank_account_id' => $bank->id,
                'reference' => $payment->reference,
                'description' => "Education fee payment for {$invoice->education?->orphan?->full_name} ({$invoice->period})",
                'amount' => $payment->amount,
                'type' => 'education_fee_payment',
                'date' => $payment->payment_date,
                'is_system' => true,
                'transactionable_type' => EducationFeePayment::class,
                'transactionable_id' => $payment->id,
            ]);

            $invoice->refresh()->refreshPaymentStatus();

            return $payment;
        });
    }

    public function payOutstandingBalance(EducationFeeInvoice $invoice, array $data): EducationFeePayment
    {
        $balance = max(0, (float) $invoice->fresh()->balance);

        if ($balance <= 0) {
            $invoice->refreshPaymentStatus();

            throw ValidationException::withMessages([
                'amount' => 'This invoice has no outstanding balance.',
            ]);
        }

        return $this->recordPayment($invoice, [
            ...$data,
            'amount' => $balance,
        ]);
    }

    public function refreshStatus(EducationFeeInvoice $invoice): EducationFeeInvoice
    {
        $invoice->refreshPaymentStatus();

        return $invoice->fresh();
    }

    public function void(EducationFeeInvoice $invoice, string $reason): EducationFeeInvoice
    {
        return DB::transaction(function () use ($invoice, $reason): EducationFeeInvoice {
            $invoice = EducationFeeInvoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->isVoided()) {
                return $invoice;
            }

            foreach ($invoice->payments()->with(['bankAccount', 'transaction'])->get() as $payment) {
                if ($payment->bankAccount) {
                    $bank = BankAccount::query()
                        ->whereKey($payment->bank_account_id)
                        ->lockForUpdate()
                        ->first();

                    $bank?->credit((float) $payment->amount);

                    Transaction::create([
                        'bank_account_id' => $payment->bank_account_id,
                        'reference' => Transaction::generateReference('education_fee_payment_void'),
                        'description' => "Void reversal for education fee payment {$payment->reference}: {$reason}",
                        'amount' => $payment->amount,
                        'type' => 'education_fee_payment_void',
                        'date' => now(),
                        'is_system' => true,
                        'transactionable_type' => EducationFeePayment::class,
                        'transactionable_id' => $payment->id,
                    ]);
                }

                $payment->delete();
            }

            $invoice->forceFill([
                'status' => EducationFeeInvoice::STATUS_VOID,
                'void_reason' => $reason,
                'voided_at' => now(),
            ])->saveQuietly();

            return $invoice->fresh();
        });
    }
}
