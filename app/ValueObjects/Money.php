<?php

namespace App\ValueObjects;

use InvalidArgumentException;

readonly class Money
{
    public function __construct(
        private int $cents,
        private string $currency = 'USD'
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }
    }

    public static function fromDecimal(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function toDecimal(): float
    {
        return $this->cents / 100;
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->cents - $other->cents, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->cents > $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function format(): string
    {
        return number_format($this->toDecimal(), 2);
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
    }
}
