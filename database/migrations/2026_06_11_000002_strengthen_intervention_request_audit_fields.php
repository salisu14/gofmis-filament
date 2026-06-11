<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('intervention_requests', 'requested_level')) {
                $table->string('requested_level')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('intervention_requests', 'requested_amount')) {
                $table->decimal('requested_amount', 15, 2)->nullable()->after('requested_level');
            }

            if (! Schema::hasColumn('intervention_requests', 'notes')) {
                $table->text('notes')->nullable()->after('requested_amount');
            }

            if (! Schema::hasColumn('intervention_requests', 'supporting_documents')) {
                $table->json('supporting_documents')->nullable()->after('notes');
            }
        });

        Schema::table('interventions', function (Blueprint $table) {
            if (! Schema::hasColumn('interventions', 'notes')) {
                $table->text('notes')->nullable()->after('support_document_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intervention_requests', function (Blueprint $table) {
            foreach (['supporting_documents', 'notes', 'requested_amount', 'requested_level'] as $column) {
                if (Schema::hasColumn('intervention_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('interventions', function (Blueprint $table) {
            if (Schema::hasColumn('interventions', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
