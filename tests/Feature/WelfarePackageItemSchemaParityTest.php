<?php

use Illuminate\Support\Facades\Schema;

test('welfare_package_items table schema has category_id column removed', function () {
    expect(Schema::hasTable('welfare_package_items'))->toBeTrue();
    expect(Schema::hasColumn('welfare_package_items', 'category_id'))->toBeFalse();
    expect(Schema::getColumnListing('welfare_package_items'))->toContain(
        'id',
        'welfare_package_id',
        'item_id',
        'quantity_per_family',
        'notes',
        'created_at',
        'updated_at'
    );
});
