<?php

namespace Tests\Feature;

use App\Models\CompanyInformation;
use App\Models\User;
use App\Services\DocumentBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyInformationDocumentBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
    }

    public function test_document_branding_service_uses_configured_company_name_and_details(): void
    {
        CompanyInformation::query()->delete();
        $company = CompanyInformation::create([
            'id' => 1,
            'company_name' => 'Custom Test Foundation',
            'display_name' => 'Custom Test Foundation',
            'trading_name' => 'Custom Test Foundation',
            'address_line_1' => '100 Custom Way',
            'city' => 'Garko',
            'state_province' => 'Kano',
            'country_code' => 'NG',
            'phone_no' => '+2348000000000',
            'email' => 'contact@customtest.org',
            'website' => 'https://customtest.org',
            'registration_no' => 'CAC/IT/99999',
        ]);

        $service = app(DocumentBrandingService::class);
        $context = $service->getDocumentContext('Test Title', 'REF-001');

        $this->assertEquals('Custom Test Foundation', $context['organisation_name']);
        $this->assertStringContainsString('100 Custom Way', $context['address']);
        $this->assertEquals('+2348000000000', $context['phone']);
        $this->assertEquals('contact@customtest.org', $context['email']);
        $this->assertEquals('https://customtest.org', $context['website']);
        $this->assertEquals('CAC/IT/99999', $context['registration_number']);
    }

    public function test_missing_logo_does_not_cause_http_500(): void
    {
        CompanyInformation::query()->delete();
        CompanyInformation::create([
            'id' => 1,
            'company_name' => 'No Logo Foundation',
            'logo_path' => null,
        ]);

        $service = app(DocumentBrandingService::class);
        $context = $service->getDocumentContext();

        $view = view('pdf.layouts.official-document', [
            'documentTitle' => 'Test Without Logo',
            'companyContext' => $context,
        ])->render();

        $this->assertStringContainsString('No Logo Foundation', $view);
    }

    public function test_changing_company_information_dynamically_updates_document_output(): void
    {
        CompanyInformation::query()->delete();
        $company = CompanyInformation::create([
            'id' => 1,
            'company_name' => 'Original Foundation Name',
        ]);

        $service = app(DocumentBrandingService::class);
        $context1 = $service->getDocumentContext();
        $this->assertEquals('Original Foundation Name', $context1['organisation_name']);

        $company->update(['company_name' => 'Updated Brand Foundation Name']);
        $context2 = $service->getDocumentContext();
        $this->assertEquals('Updated Brand Foundation Name', $context2['organisation_name']);
    }
}
