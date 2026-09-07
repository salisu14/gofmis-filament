<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\OutOfPocketExpenditure;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutOfPocketExpenditureService
{
    public function submit(OutOfPocketExpenditure $record, User $user): OutOfPocketExpenditure
    {
        if (! $record->isDraft()) {
            throw ValidationException::withMessages([
                'approval_status' => 'Only draft expenditures can be submitted for review.',
            ]);
        }

        $record->update([
            'approval_status' => 'submitted',
            'submitted_by_id' => $user->id,
        ]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($user)
                ->log('submitted out of pocket expenditure for approval');
        }

        return $record->fresh();
    }

    public function approve(OutOfPocketExpenditure $record, User $user): OutOfPocketExpenditure
    {
        if (! $record->isSubmitted()) {
            throw ValidationException::withMessages([
                'approval_status' => 'Only submitted expenditures can be approved.',
            ]);
        }

        // Block self-approval if incurred by same user
        if ($record->incurred_by_user_id === $user->id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'approved_by_id' => 'Self-approval of out-of-pocket expenditure is prohibited.',
            ]);
        }

        $reimbursementStatus = $record->reimbursement_required ? 'pending' : 'not_required';

        $record->update([
            'approval_status' => 'approved',
            'approved_by_id' => $user->id,
            'approved_at' => now(),
            'reimbursement_status' => $reimbursementStatus,
        ]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($user)
                ->log('approved out of pocket expenditure');
        }

        return $record->fresh();
    }

    public function reject(OutOfPocketExpenditure $record, User $user, string $reason): OutOfPocketExpenditure
    {
        if (! $record->isSubmitted()) {
            throw ValidationException::withMessages([
                'approval_status' => 'Only submitted expenditures can be rejected.',
            ]);
        }

        if (empty(trim($reason))) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        $record->update([
            'approval_status' => 'rejected',
            'rejected_by_id' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => trim($reason),
        ]);

        if (function_exists('activity')) {
            activity()
                ->performedOn($record)
                ->causedBy($user)
                ->log('rejected out of pocket expenditure');
        }

        return $record->fresh();
    }

    public function reimburse(OutOfPocketExpenditure $record, BankAccount $bankAccount, User $user): OutOfPocketExpenditure
    {
        return DB::transaction(function () use ($record, $bankAccount, $user) {
            /** @var OutOfPocketExpenditure $lockedRecord */
            $lockedRecord = OutOfPocketExpenditure::query()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRecord->isApproved()) {
                throw ValidationException::withMessages([
                    'approval_status' => 'Only approved expenditures can be reimbursed.',
                ]);
            }

            if (! $lockedRecord->reimbursement_required || $lockedRecord->reimbursement_status === 'not_required') {
                throw ValidationException::withMessages([
                    'reimbursement_status' => 'This expenditure does not require reimbursement.',
                ]);
            }

            if ($lockedRecord->isReimbursed() || ! empty($lockedRecord->reimbursement_transaction_id)) {
                throw ValidationException::withMessages([
                    'reimbursement_status' => 'This expenditure has already been reimbursed.',
                ]);
            }

            /** @var BankAccount $lockedBankAccount */
            $lockedBankAccount = BankAccount::query()
                ->whereKey($bankAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBankAccount->canFundOutOfPocketReimbursement((float) $lockedRecord->amount)) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'The selected bank account is not permitted for out-of-pocket reimbursements.',
                ]);
            }

            // Create canonical transaction (postToBank runs automatically if is_system is false)
            $transaction = Transaction::create([
                'bank_account_id' => $lockedBankAccount->id,
                'amount' => $lockedRecord->amount,
                'type' => 'out_of_pocket_reimbursement',
                'description' => "Out of Pocket Reimbursement: {$lockedRecord->reference} - {$lockedRecord->description}",
                'reference' => Transaction::generateReference('out_of_pocket_reimbursement'),
                'date' => now(),
                'is_system' => false,
                'transactionable_type' => OutOfPocketExpenditure::class,
                'transactionable_id' => $lockedRecord->id,
            ]);

            $lockedRecord->update([
                'reimbursement_status' => 'reimbursed',
                'reimbursement_bank_account_id' => $lockedBankAccount->id,
                'reimbursement_transaction_id' => $transaction->id,
                'reimbursed_by_id' => $user->id,
                'reimbursed_at' => now(),
            ]);

            if (function_exists('activity')) {
                activity()
                    ->performedOn($lockedRecord)
                    ->causedBy($user)
                    ->log('reimbursed out of pocket expenditure via bank account '.$lockedBankAccount->account_name);
            }

            return $lockedRecord->fresh();
        });
    }
}
