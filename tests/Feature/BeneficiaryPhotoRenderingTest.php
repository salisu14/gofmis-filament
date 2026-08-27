<?php

use App\Models\Orphan;
use App\Models\Widow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('resolves null when picture_url is blank', function () {
    $orphan = new Orphan(['picture_url' => null]);

    expect($orphan->profile_photo_url)->toBeNull()
        ->and($orphan->profile_photo_data_uri)->toBeNull();
});

it('returns absolute URL directly if picture_url is an absolute URL', function () {
    $url = 'https://example.com/avatar.jpg';
    $orphan = new Orphan(['picture_url' => $url]);

    expect($orphan->profile_photo_url)->toBe($url)
        ->and($orphan->profile_photo_data_uri)->toBeNull(); // Absolute URLs are not processed for local data URIs
});

it('generates a public storage URL for relative paths', function () {
    $orphan = new Orphan(['picture_url' => 'orphans/test-photo.jpg']);

    $expectedUrl = Storage::disk('public')->url('orphans/test-photo.jpg');

    expect($orphan->profile_photo_url)->toBe($expectedUrl);
});

it('generates a base64 data URI for existing local files', function () {
    // Create a dummy image file on the fake disk
    $file = UploadedFile::fake()->image('avatar.jpg');
    $path = $file->store('orphans', 'public');

    $orphan = new Orphan(['picture_url' => $path]);

    $dataUri = $orphan->profile_photo_data_uri;

    expect($dataUri)->not->toBeNull()
        ->and($dataUri)->toStartWith('data:image/jpeg;base64,');
});

it('returns null data URI if file does not exist on disk', function () {
    $orphan = new Orphan(['picture_url' => 'orphans/missing.jpg']);

    expect($orphan->profile_photo_data_uri)->toBeNull();
});

it('applies HasProfilePhoto trait consistently to Widow model', function () {
    $widow = new Widow(['picture_url' => 'widows/test-photo.jpg']);

    $expectedUrl = Storage::disk('public')->url('widows/test-photo.jpg');

    expect($widow->profile_photo_url)->toBe($expectedUrl);
});

it('rejects ../ traversal in the data URI helper', function () {
    $orphan = new Orphan(['picture_url' => '../../../../etc/passwd']);

    expect($orphan->profile_photo_data_uri)->toBeNull();
});

it('rejects an absolute filesystem path in the data URI helper', function () {
    $orphan = new Orphan(['picture_url' => '/etc/passwd']);

    expect($orphan->profile_photo_data_uri)->toBeNull();
});

it('rejects a symlink escaping the public disk in the data URI helper', function () {
    // Create a real file outside the fake public disk.
    $outsideDir = sys_get_temp_dir().'/gofmis-photo-contained-'.uniqid();
    @mkdir($outsideDir, 0777, true);
    $outsideFile = $outsideDir.'/secret.txt';
    file_put_contents($outsideFile, 'super-secret');

    $root = Storage::disk('public')->path('');
    $link = $root.'/escape.png';

    try {
        if (file_exists($link)) {
            @unlink($link);
        }
        @symlink($outsideFile, $link);

        $orphan = new Orphan(['picture_url' => 'escape.png']);

        if (! file_exists($link) || ! is_link($link)) {
            // Symlinks unavailable on this platform; behave as a valid local file.
            $dataUri = $orphan->profile_photo_data_uri;
            expect($dataUri)->toBeNull()->or->toStartWith('data:');
        } else {
            // Realpath resolves outside the public disk root -> must be rejected.
            expect($orphan->profile_photo_data_uri)->toBeNull();
        }
    } finally {
        @unlink($link);
        @unlink($outsideFile);
        @rmdir($outsideDir);
    }
});

it('still produces a data URI for an ordinary local photo', function () {
    $file = UploadedFile::fake()->image('portrait.jpg');
    $path = $file->store('orphans', 'public');

    $orphan = new Orphan(['picture_url' => $path]);

    expect($orphan->profile_photo_data_uri)->toStartWith('data:image/');
});
