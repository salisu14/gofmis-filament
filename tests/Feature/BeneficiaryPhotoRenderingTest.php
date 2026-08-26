<?php

use App\Models\Orphan;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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

