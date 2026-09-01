<?php

use App\Models\CompanyInformation;
use App\Models\User;
use App\Services\Company\CompanyInformationService;
use App\Services\DocumentBrandingService;
use App\Services\ReportSignatoryResolverService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    CompanyInformation::setInstance([
        'company_name' => 'Garko Orphans Foundation',
        'address_line_1' => 'Shop No.1, Garko Juma\'at Mosque, Garko Local Government, Kano',
        'report_signatory_name' => null,
        'report_signatory_title' => null,
        'report_signature_path' => null,
    ]);
});

test('report footer displays Welfare Department and no report renders Finance Department', function () {
    $service = app(DocumentBrandingService::class);
    $context = $service->getDocumentContext('Test Report');

    expect($context['department'])->toBe('Welfare Department');
    expect($context['footer_text'])->toContain('Welfare Department');
    expect($context['footer_text'])->not->toContain('Finance Department');

    $view = view('pdf.layouts.official-document', [
        'documentTitle' => 'Test Official Report',
        'company' => $context,
    ])->render();

    expect($view)->toContain('Welfare Department');
    expect($view)->not->toContain('Finance Department');
});

test('authorized admin can save signatory name, title, and signature image to private storage', function () {
    Role::firstOrCreate(['name' => 'admin'], ['uuid' => (string) Str::uuid()]);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $file = UploadedFile::fake()->image('signature.png', 200, 100);

    actingAs($admin);

    $service = app(CompanyInformationService::class);
    $path = $service->storeSignature($file);

    expect($path)->toBeString();
    // Signature must be saved to private 'local' disk, NOT public disk
    expect(Storage::disk('local')->exists($path))->toBeTrue();
    expect(Storage::disk('public')->exists($path))->toBeFalse();

    $updated = $service->update([
        'company_name' => 'Garko Orphans Foundation',
        'report_signatory_name' => 'Dr. Aminu Garko',
        'report_signatory_title' => 'Head of Welfare',
        'report_signature_path' => $path,
    ]);

    expect($updated->report_signatory_name)->toBe('Dr. Aminu Garko');
    expect($updated->report_signatory_title)->toBe('Head of Welfare');
    expect($updated->report_signature_path)->toBe($path);
});

test('ReportSignatoryResolverService resolves normalized signatory structure from private storage', function () {
    $file = UploadedFile::fake()->image('sign.png', 200, 100);
    $service = app(CompanyInformationService::class);
    $path = $service->storeSignature($file);

    CompanyInformation::instance()->update([
        'report_signatory_name' => 'Dr. Aminu Garko',
        'report_signatory_title' => 'Executive Director',
        'report_signature_path' => $path,
    ]);

    $resolver = app(ReportSignatoryResolverService::class);
    $resolved = $resolver->resolveReportSignatory('orphan_dossier');

    expect($resolved)->toBeArray();
    expect($resolved['name'])->toBe('Dr. Aminu Garko');
    expect($resolved['title'])->toBe('Executive Director');
    expect($resolved['source'])->toBe('company_default');
    expect($resolved['signature_path'])->toBe($path);
    expect($resolved['signature_data_uri'])->toContain('data:image/png;base64,');
});

test('unauthorized user cannot modify signature configuration via Filament resource authorization', function () {
    Role::firstOrCreate(['name' => 'regular_user'], ['uuid' => (string) Str::uuid()]);
    $user = User::factory()->create();
    $user->assignRole('regular_user');

    actingAs($user);

    $canEdit = \App\Filament\Resources\CompanyInformation\CompanyInformationResource::canEdit(CompanyInformation::instance());
    expect($canEdit)->toBeFalse();
});

test('signature file validation rejects non-image or invalid uploads', function () {
    $pdfFile = UploadedFile::fake()->create('malicious.pdf', 100, 'application/pdf');

    $service = app(CompanyInformationService::class);

    expect(fn () => $service->storeSignature($pdfFile))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('reports render correctly when signature exists and consume normalized signatory context', function () {
    $file = UploadedFile::fake()->image('sign.png', 200, 100);
    $service = app(CompanyInformationService::class);
    $path = $service->storeSignature($file);

    $company = CompanyInformation::instance();
    $company->update([
        'report_signatory_name' => 'Hajiya Fatima',
        'report_signatory_title' => 'Welfare Director',
        'report_signature_path' => $path,
    ]);

    $context = app(DocumentBrandingService::class)->getDocumentContext('Test Report');

    expect($context['signatory'])->toBeArray();
    expect($context['signatory']['name'])->toBe('Hajiya Fatima');
    expect($context['signatory']['title'])->toBe('Welfare Director');
    expect($context['signatory']['signature_data_uri'])->not->toBeNull();

    $view = view('pdf.layouts.official-document', [
        'documentTitle' => 'Test Official Report',
        'company' => $context,
    ])->render();

    expect($view)->toContain('Hajiya Fatima');
    expect($view)->toContain('Welfare Director');
    expect($view)->toContain('data:image/png;base64,');
});

test('reports render correctly when signature is absent', function () {
    CompanyInformation::instance()->update([
        'report_signatory_name' => null,
        'report_signatory_title' => null,
        'report_signature_path' => null,
    ]);

    $context = app(DocumentBrandingService::class)->getDocumentContext('Test Report');

    expect($context['signatory']['signature_data_uri'])->toBeNull();

    $view = view('pdf.layouts.official-document', [
        'documentTitle' => 'Test Official Report',
        'company' => $context,
    ])->render();

    expect($view)->toContain('Welfare Department');
    expect($view)->not->toContain('Finance Department');
});

test('existing company information details remain intact after signature updates', function () {
    $company = CompanyInformation::instance();
    $originalName = $company->company_name;
    $originalAddress = $company->address_line_1;

    $service = app(CompanyInformationService::class);
    $service->update([
        'company_name' => $originalName,
        'address_line_1' => $originalAddress,
        'report_signatory_name' => 'New Signatory',
    ]);

    $fresh = CompanyInformation::instance()->fresh();
    expect($fresh->company_name)->toBe($originalName);
    expect($fresh->address_line_1)->toBe($originalAddress);
    expect($fresh->report_signatory_name)->toBe('New Signatory');
});
