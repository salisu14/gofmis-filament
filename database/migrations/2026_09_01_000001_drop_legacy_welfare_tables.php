<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['welfare', 'deceased_welfare'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $count = DB::table($table)->count();
            if ($count > 0) {
                throw new RuntimeException("Cannot drop legacy table '{$table}' because it contains {$count} rows. Please archive data before proceeding.");
            }
        }
        Schema::dropIfExists('deceased_welfare');
        Schema::dropIfExists('welfare');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('welfare')) {
            Schema::create('welfare', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('deceased_id')->constrained('deceased');
                $table->enum('status', ['PENDING', 'APPROVED', 'COLLECTED'])->default('PENDING');
                $table->timestamp('collected_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('deceased_welfare')) {
            Schema::create('deceased_welfare', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('deceased_id')->constrained('deceased');
                $table->foreignId('welfare_id')->constrained('welfare');
                $table->timestamps();
            });
        }
    }
};
?>
