<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BankAccountsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::first();

        BankAccount::create([
            'id' => Str::uuid(),
            'account_name' => 'WRL',
            'account_number' => '1230000178',
            'opening_balance' => 99997.23,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
