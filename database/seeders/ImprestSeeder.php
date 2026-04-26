<?php

namespace Database\Seeders;

use App\Enums\TransactionStatus;
use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImprestSeeder extends Seeder
{
    public function run(): void
    {
        $custodian = User::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Imprest Custodian'
        ]);

        $supervisor = User::factory()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Fund Supervisor'
        ]);

        $fund = ImprestFund::factory()->create([
            'id' => (string) Str::uuid(),
            'custodian_id' => $custodian->id,
            'authorized_amount' => 1000.00,
            'current_balance' => 1000.00,
        ]);

        // ✅ Create transactions
        $prefix = 'VCH-' . now()->format('Ymd');
        $counter = 1;

        ImprestTransaction::factory()
            ->count(15)
            ->make()
            ->each(function ($transaction) use ($fund, $custodian, $supervisor, $prefix, &$counter) {

                $transaction->id = (string) Str::uuid();
                $transaction->fund_id = $fund->id;
                $transaction->custodian_id = $custodian->id;
                $transaction->deceased_id = null;

                // ✅ GUARANTEED UNIQUE
                $transaction->voucher_no = $prefix . '-' . str_pad($counter++, 4, '0', STR_PAD_LEFT);

                $transaction->save();

                if (fake()->boolean(70)) {
                    $transaction->update([
                        'status' => TransactionStatus::ACTIVE->value,
                        'approved_by' => $supervisor->id,
                        'approved_at' => now(),
                    ]);

                    $fund->decrement('current_balance', $transaction->total_price);
                }
            });

        // ✅ Voided transactions
        ImprestTransaction::factory()
            ->count(3)
            ->make()
            ->each(function ($transaction) use ($fund, $custodian, $prefix, &$counter) {

                $transaction->id = (string) Str::uuid();
                $transaction->fund_id = $fund->id;
                $transaction->custodian_id = $custodian->id;
                $transaction->deceased_id = null;

                $transaction->voucher_no = $prefix . '-' . str_pad($counter++, 4, '0', STR_PAD_LEFT);

                $transaction->save();
            });
    }
}
