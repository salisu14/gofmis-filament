<?php

namespace App\Models\Scopes\Imprest;

use Illuminate\Database\Eloquent\Builder;

class ImprestTransactionQueryBuilder extends Builder
{
    public function withReceipt(): self
    {
        return $this->where('receipt_attached', true);
    }

    public function withoutReceipt(): self
    {
        return $this->where('receipt_attached', false);
    }

    public function byCategory(string $category): self
    {
        return $this->where('category', $category);
    }

    public function byPaymentMethod(string $method): self
    {
        return $this->where('payment_method', $method);
    }

    public function exceedingAmount(float $amount): self
    {
        return $this->where('total_price', '>', $amount);
    }

    public function pendingApproval(): self
    {
        return $this->where('status', 'pending')->whereNull('approved_at');
    }

    public function approvedToday(): self
    {
        return $this->where('status', 'active')
            ->whereDate('approved_at', today());
    }

    public function totalSpent(): float
    {
        return (float) $this->sum('total_price');
    }
}
