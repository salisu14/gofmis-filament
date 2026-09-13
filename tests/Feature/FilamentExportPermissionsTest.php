<?php

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('configures private local filesystem permissions for group accessibility', function () {
    $permissions = config('filesystems.disks.local.permissions');

    expect($permissions)->toBeArray()
        ->and($permissions['file']['private'])->toBe(0660)
        ->and($permissions['dir']['private'])->toBe(0770);
});

it('creates private files and directories with group-accessible permission masks on local disk', function () {
    $diskConfig = config('filesystems.disks.local');
    $testDirName = 'test_private_perms_'.bin2hex(random_bytes(4));
    config(['filesystems.disks.local_permissions_test' => array_merge($diskConfig, [
        'root' => storage_path('app/'.$testDirName),
    ])]);

    $disk = Storage::disk('local_permissions_test');
    $disk->put('filament_exports/test_dir/file.txt', 'test content', 'private');

    $filePath = storage_path('app/'.$testDirName.'/filament_exports/test_dir/file.txt');
    $dirPath = dirname($filePath);

    expect(file_exists($filePath))->toBeTrue();

    // Verify flysystem applied 0660 and 0770 permission masks
    $filePerms = fileperms($filePath) & 0777;
    $dirPerms = fileperms($dirPath) & 0777;

    // Check that group read (0040) is enabled on file, and group read+exec (0050) is enabled on directory
    expect($filePerms & 0040)->not->toBe(0)
        ->and($dirPerms & 0050)->not->toBe(0);

    // Clean up temporary test directory
    $disk->deleteDirectory('filament_exports');
    @rmdir(storage_path('app/'.$testDirName));
});

it('verifies signed export download authorization behavior', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'status' => UserStatus::ACTIVE,
    ]);

    $export = Export::create([
        'user_id' => $user->id,
        'exporter' => \App\Filament\Exports\DeceasedExporter::class,
        'file_name' => 'export-test-deceaseds',
        'file_disk' => 'local',
        'total_rows' => 1,
        'processed_rows' => 1,
        'successful_rows' => 1,
        'completed_at' => now(),
    ]);

    $disk = Storage::disk('local');
    $disk->put("filament_exports/{$export->id}/headers.csv", "RegNo\n", 'private');
    $disk->put("filament_exports/{$export->id}/0000000000000001.csv", "DEC/001\n", 'private');

    $action = ExportFormat::Csv->getDownloadNotificationAction($export, 'web');
    $signedUrl = $action->getUrl();

    // Unauthenticated request should return 401
    $this->get($signedUrl)->assertStatus(401);

    // Authenticated user (owner of export) should be able to download
    $this->actingAs($user)->get($signedUrl)->assertSuccessful();

    // Clean up test export storage directory
    $export->deleteFileDirectory();
});
