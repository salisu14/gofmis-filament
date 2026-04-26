<?php

namespace App\Data\Imprest;

use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use Carbon\Carbon;

readonly class CreateTransactionDto
{
    public function __construct(
        public string $fundId,
        public Carbon $date,
        public string $deceasedId,
        public string $name,
        public string $itemService,
        public float $quantity,
        public float $unitPrice,
        public TransactionCategory $category,
        public PaymentMethod $paymentMethod,
        public bool $receiptAttached = false,
        public ?string $voucherNo = null,
    ) {}

    public function toArray(): array
    {
        return [
            'fund_id' => $this->fundId,
            'date' => $this->date,
            'deceased_id' => $this->deceasedId,
            'name' => $this->name,
            'item_service' => $this->itemService,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'category' => $this->category->value,
            'payment_method' => $this->paymentMethod->value,
            'receipt_attached' => $this->receiptAttached,
            'voucher_no' => $this->voucherNo,
        ];
    }
}
