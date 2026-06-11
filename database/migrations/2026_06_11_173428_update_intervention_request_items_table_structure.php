<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_request_items', function (Blueprint $table) {
            // Add item relationship and snapshot
            if (!Schema::hasColumn('intervention_request_items', 'item_id')) {
                $table->uuid('item_id')->nullable()->after('intervention_request_id');
                $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
            }
            if (!Schema::hasColumn('intervention_request_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('item_id');
            }

            // Add details
            if (!Schema::hasColumn('intervention_request_items', 'specification')) {
                $table->string('specification')->nullable()->after('item_name');
            }
            if (!Schema::hasColumn('intervention_request_items', 'orphan_class')) {
                $table->string('orphan_class')->nullable()->after('specification');
            }

            // Add quantities
            if (!Schema::hasColumn('intervention_request_items', 'quantity_requested')) {
                $table->integer('quantity_requested')->default(0)->after('orphan_class');
            }
            if (!Schema::hasColumn('intervention_request_items', 'quantity_fulfilled')) {
                $table->integer('quantity_fulfilled')->default(0)->after('quantity_requested');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intervention_request_items', function (Blueprint $table) {
            // We generally don't want to drop these on rollback, but if you need to:
            // $table->dropForeign(['item_id']);
            // $table->dropColumn(['item_id', 'item_name', 'specification', 'orphan_class', 'quantity_requested', 'quantity_fulfilled']);
        });
    }
};
