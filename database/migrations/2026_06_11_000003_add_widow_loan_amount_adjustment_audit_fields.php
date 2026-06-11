<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('widow_loans', 'original_principal_amount')) {
                $table->decimal('original_principal_amount', 15, 2)->nullable()->after('principal_amount');
            }

            if (! Schema::hasColumn('widow_loans', 'amount_adjustment_note')) {
                $table->text('amount_adjustment_note')->nullable()->after('original_principal_amount');
            }

            if (! Schema::hasColumn('widow_loans', 'amount_adjusted_by')) {
                $table->foreignUuid('amount_adjusted_by')
                    ->nullable()
                    ->after('amount_adjustment_note')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('widow_loans', 'amount_adjusted_at')) {
                $table->timestamp('amount_adjusted_at')->nullable()->after('amount_adjusted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'amount_adjusted_by')) {
                $table->dropConstrainedForeignId('amount_adjusted_by');
            }

            foreach (['amount_adjusted_at', 'amount_adjustment_note', 'original_principal_amount'] as $column) {
                if (Schema::hasColumn('widow_loans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
