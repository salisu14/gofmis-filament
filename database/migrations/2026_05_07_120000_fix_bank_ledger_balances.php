<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\BankAccount;

return new class extends Migration
{
    public function up(): void
    {
        // Fix existing accounts where ledger_balance is 0 but opening_balance exists
        BankAccount::where('ledger_balance', 0)
            ->where('opening_balance', '>', 0)
            ->each(function ($account) {
                $account->ledger_balance = $account->opening_balance;
                $account->save();
            });
    }

    public function down(): void
    {
        // No reverse logic needed for data fix
    }
};
