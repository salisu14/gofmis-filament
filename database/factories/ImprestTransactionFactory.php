<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Enums\TransactionStatus;
use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImprestTransactionFactory extends Factory
{
    protected $model = ImprestTransaction::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 5, 50);

        return [
            'id' => (string) Str::uuid(),

            // REMOVE factory defaults for relationships
            'fund_id' => null,
            'custodian_id' => null,

            'date' => fake()->dateTimeBetween('-30 days', 'now'),

            // FIX: must be UUID or nullable
            'deceased_id' => null,

            'name' => fake()->name(),
            'item_service' => fake()->words(3, true),

            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,

            'voucher_no' => null, // let model generate

            'receipt_attached' => fake()->boolean(80),

            'approved_by' => null,

            'category' => fake()->randomElement(
                array_map(fn($c) => strtolower($c->value), TransactionCategory::cases())
            ),

            // 🔥 CRITICAL FIX
            'payment_method' => fake()->randomElement(
                array_map(fn($p) => strtolower($p->value), PaymentMethod::cases())
            ),

            'status' => TransactionStatus::PENDING->value,

            'void_reason' => null,
            'approved_at' => null,
            'voided_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::ACTIVE->value,
            'approved_by' => (string) Str::uuid(), // or pass explicitly
            'approved_at' => now(),
        ]);
    }



//    public function voided(): static
//    {
//        return $this->state(fn () => [
//            'status' => TransactionStatus::VOIDED->value,
//            'void_reason' => fake()->sentence(),
//            'voided_at' => now(),
//        ]);
//    }
}
