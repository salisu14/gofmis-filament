<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'date_issued')) {
                $table->date('date_issued')->nullable()->change();
            }
            if (Schema::hasColumn('widow_loans', 'due_date')) {
                $table->date('due_date')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'date_issued')) {
                $table->date('date_issued')->nullable(false)->change();
            }
            if (Schema::hasColumn('widow_loans', 'due_date')) {
                $table->date('due_date')->nullable(false)->change();
            }
        });
    }
};
