<?php

namespace App\Services\Imprest;

use App\Enums\FundStatus;
use App\Models\ImprestAuditLog;
use App\Models\ImprestFund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImprestFundStatusService
{
    public function suspend(ImprestFund $fund, User $user, string $reason): ImprestFund
    {
        if (! $fund->canBeSuspended()) {
            throw new RuntimeException('Only active funds can be suspended.');
        }

        return $this->transition($fund, $user, FundStatus::SUSPENDED, 'fund_suspended', $reason);
    }

    public function reactivate(ImprestFund $fund, User $user, string $reason): ImprestFund
    {
        if (! $fund->canBeReactivated()) {
            throw new RuntimeException('Only suspended funds can be reactivated.');
        }

        return $this->transition($fund, $user, FundStatus::ACTIVE, 'fund_reactivated', $reason);
    }

    public function close(ImprestFund $fund, User $user, string $reason): ImprestFund
    {
        if (! $fund->canBeClosed()) {
            $blockers = implode(' and ', $fund->statusBlockersForClosure());

            throw new RuntimeException("Fund cannot be closed while it has {$blockers}.");
        }

        return $this->transition($fund, $user, FundStatus::CLOSED, 'fund_closed', $reason);
    }

    private function transition(ImprestFund $fund, User $user, FundStatus $targetStatus, string $action, string $reason): ImprestFund
    {
        return DB::transaction(function () use ($fund, $user, $targetStatus, $action, $reason): ImprestFund {
            $fund = ImprestFund::query()->lockForUpdate()->findOrFail($fund->id);
            $oldStatus = $fund->status;
            $oldNotes = $fund->notes;

            $fund->update([
                'status' => $targetStatus->value,
                'notes' => $this->appendStatusNote($oldNotes, $action, $reason, $user),
            ]);

            ImprestAuditLog::create([
                'auditable_type' => ImprestFund::class,
                'auditable_id' => $fund->id,
                'user_id' => $user->id,
                'action' => $action,
                'old_values' => [
                    'status' => $oldStatus,
                    'notes' => $oldNotes,
                ],
                'new_values' => [
                    'status' => $targetStatus->value,
                    'reason' => $reason,
                    'balance_at_change' => (float) $fund->current_balance,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            return $fund->fresh();
        });
    }

    private function appendStatusNote(?string $notes, string $action, string $reason, User $user): string
    {
        $label = str($action)->replace('_', ' ')->title();
        $entry = sprintf('[%s by %s on %s] %s', $label, $user->name, now()->format('Y-m-d H:i'), $reason);

        return trim(implode(PHP_EOL, array_filter([$notes, $entry])));
    }
}
