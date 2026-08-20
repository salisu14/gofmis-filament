<?php

use App\Enums\IllnessCategory;
use App\Models\Illness;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('illness')->nullable()->change();
        });

        // Data migration: backfill illness_id for historical rows that have illness text but missing illness_id
        if (Schema::hasTable('prescriptions') && Schema::hasTable('illnesses')) {
            $rowsWithoutFk = DB::table('prescriptions')
                ->whereNull('illness_id')
                ->whereNotNull('illness')
                ->where('illness', '!=', '')
                ->get();

            foreach ($rowsWithoutFk as $row) {
                $illnessName = trim($row->illness);
                if (empty($illnessName)) {
                    continue;
                }

                $illness = Illness::firstOrCreate(
                    ['name' => $illnessName],
                    [
                        'category' => IllnessCategory::Other,
                        'description' => 'Migrated from legacy illness text field',
                    ]
                );

                DB::table('prescriptions')
                    ->where('id', $row->id)
                    ->update(['illness_id' => $illness->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('illness')->nullable(false)->change();
        });
    }
};
