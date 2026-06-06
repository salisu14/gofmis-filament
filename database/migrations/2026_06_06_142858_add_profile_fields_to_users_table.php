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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');

            $table->string('alternate_phone', 20)->nullable()->after('phone');

            $table->string('designation')->nullable()->after('alternate_phone');
            // Coordinator, Admin, Staff, Accountant, etc.

            $table->string('address')->nullable()->after('designation');

            $table->string('photo')->nullable()->after('address');

            $table->date('date_of_birth')->nullable()->after('photo');

            $table->enum('gender', ['male', 'female'])->nullable()->after('date_of_birth');

            $table->boolean('is_active')
                ->default(true)
                ->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'alternate_phone',
                'designation',
                'address',
                'photo',
                'date_of_birth',
                'gender',
                'is_active',
            ]);
        });
    }
};
