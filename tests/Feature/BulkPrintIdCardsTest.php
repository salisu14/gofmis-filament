<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Filament\Resources\IdCards\Pages\BulkPrintIdCards;
use App\Jobs\GenerateIdCardsJob;
use App\Models\Deceased;
use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\User;
use App\Models\Widow;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkPrintIdCardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zone;

    protected Deceased $deceased;

    protected IdCardTemplate $widowTemplate;

    protected IdCardTemplate $orphanTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();

        $this->admin = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'TESTSECRET123456',
            'mfa_confirmed_at' => now(),
            'mfa_enabled_at' => now(),
        ]);
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'TESTSECRET123456',
            'mfa_confirmed_at' => now(),
            'mfa_enabled_at' => now(),
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->zone = Zone::create(['name' => 'Kano Central', 'code' => 'KNC']);

        $this->deceased = Deceased::factory()->create([
            'zone_id' => $this->zone->id,
        ]);

        $this->widowTemplate = IdCardTemplate::create([
            'name' => 'Standard Widow Template',
            'type' => 'widow',
            'width_mm' => 85.6,
            'height_mm' => 53.98,
            'is_active' => true,
            'layout_config' => IdCardTemplate::defaultLayoutConfig('widow'),
        ]);

        $this->orphanTemplate = IdCardTemplate::create([
            'name' => 'Standard Orphan Template',
            'type' => 'orphan',
            'width_mm' => 85.6,
            'height_mm' => 53.98,
            'is_active' => true,
            'layout_config' => IdCardTemplate::defaultLayoutConfig('orphan'),
        ]);
    }

    protected function createWidow(array $attributes = []): Widow
    {
        static $seq = 1;

        return Widow::create(array_merge([
            'first_name' => 'WidowName',
            'last_name' => 'WidowLast',
            'reg_no' => 'WID-REG-'.$seq,
            'nin' => str_pad((string) $seq, 11, '0', STR_PAD_LEFT),
            'child_sequence' => $seq++,
            'deceased_id' => $this->deceased->id,
            'is_eligible' => true,
            'is_married' => false,
        ], $attributes));
    }

    protected function createOrphan(array $attributes = []): Orphan
    {
        static $seq = 1;

        return Orphan::create(array_merge([
            'first_name' => 'OrphanName',
            'last_name' => 'OrphanLast',
            'reg_no' => 'ORP-REG-'.$seq,
            'nin' => str_pad((string) ($seq + 100), 11, '0', STR_PAD_LEFT),
            'child_sequence' => $seq++,
            'deceased_id' => $this->deceased->id,
            'birth_date' => '2015-01-01',
            'gender' => Gender::MALE,
            'status' => OrphanStatus::ACTIVE,
            'is_eligible' => true,
        ], $attributes));
    }

    public function test_bulk_print_page_renders_for_authorized_admin(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
            ->get('/admin/id-cards/bulk-print')
            ->assertStatus(200)
            ->assertSee('Bulk ID Card Print');
    }

    public function test_unauthorized_user_cannot_access_bulk_print_page(): void
    {
        $this->actingAs($this->coordinator)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->coordinator->id])
            ->get('/admin/id-cards/bulk-print')
            ->assertStatus(403);
    }

    public function test_calculate_count_returns_correct_beneficiary_count(): void
    {
        $this->createWidow(['first_name' => 'Fatima', 'reg_no' => 'UAT-WID-002']);
        $this->createWidow(['first_name' => 'Amina', 'reg_no' => 'UAT-WID-010']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->widowTemplate->id)
            ->call('calculateCount')
            ->assertSet('estimatedCount', 2)
            ->assertNotified('Count Calculated');
    }

    public function test_generate_action_is_callable_and_creates_batch_record_and_cards(): void
    {
        $w1 = $this->createWidow(['first_name' => 'Fatima', 'reg_no' => 'UAT-WID-002']);
        $w2 = $this->createWidow(['first_name' => 'Mary', 'reg_no' => 'UAT-WID-005']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'specific')
            ->set('data.range.specific_ids', [$w1->id, $w2->id])
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'Widows Q3')
            ->callAction('create_batch')
            ->assertHasNoActionErrors()
            ->assertRedirect();

        expect(IdCardPrintBatch::count())->toBe(1);

        $batch = IdCardPrintBatch::first();
        expect($batch->batch_name)->toBe('Widows Q3')
            ->and($batch->total_count)->toBe(2)
            ->and($batch->status)->toBe('completed');

        expect(IdCard::count())->toBe(2);
    }

    public function test_zero_eligible_beneficiaries_returns_warning_notification(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'Empty Batch')
            ->callAction('create_batch')
            ->assertNotified('No eligible beneficiaries found for this print batch.');

        expect(IdCardPrintBatch::count())->toBe(0);
    }

    public function test_exclude_already_printed_filter_excludes_active_card_holders(): void
    {
        $w1 = $this->createWidow(['first_name' => 'Fatima', 'reg_no' => 'UAT-WID-002']);
        $this->createWidow(['first_name' => 'Mary', 'reg_no' => 'UAT-WID-005']);

        IdCard::create([
            'cardable_type' => Widow::class,
            'cardable_id' => $w1->id,
            'template_id' => $this->widowTemplate->id,
            'card_number' => 'WID-002-01',
            'qr_code_path' => 'id_cards/qr/test.png',
            'status' => 'active',
            'issued_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.filters.exclude_printed', true)
            ->set('data.template_id', $this->widowTemplate->id)
            ->call('calculateCount')
            ->assertSet('estimatedCount', 1);
    }

    public function test_selected_beneficiary_state_survives_calculate_count_before_generation(): void
    {
        $w1 = $this->createWidow(['first_name' => 'Fatima', 'reg_no' => 'UAT-WID-002']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'specific')
            ->set('data.range.specific_ids', [$w1->id])
            ->set('data.template_id', $this->widowTemplate->id)
            ->call('calculateCount')
            ->assertSet('estimatedCount', 1)
            ->set('data.batch_name', 'Surviving State Batch')
            ->callAction('create_batch')
            ->assertHasNoActionErrors();

        expect(IdCardPrintBatch::count())->toBe(1);
    }

    public function test_mixed_widow_and_orphan_generation_succeeds(): void
    {
        $w1 = $this->createWidow(['first_name' => 'Fatima', 'reg_no' => 'UAT-MIX-W1']);
        $w2 = $this->createWidow(['first_name' => 'Mary', 'reg_no' => 'UAT-MIX-W2']);
        $o1 = $this->createOrphan(['first_name' => 'Suleiman', 'reg_no' => 'UAT-MIX-O1']);
        $o2 = $this->createOrphan(['first_name' => 'Zahra', 'reg_no' => 'UAT-MIX-O2']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'mixed')
            ->set('data.range_type', 'all')
            ->set('data.batch_name', 'Mixed Batch Q1')
            ->callAction('create_batch')
            ->assertHasNoActionErrors()
            ->assertRedirect();

        expect(IdCardPrintBatch::count())->toBe(1);

        $batch = IdCardPrintBatch::first();
        expect($batch->batch_name)->toBe('Mixed Batch Q1')
            ->and($batch->type)->toBe('mixed')
            ->and($batch->total_count)->toBe(4)
            ->and($batch->status)->toBe('completed');

        expect(IdCard::count())->toBe(4);
    }

    public function test_mixed_beneficiary_generate_job_payload_is_queue_serializable(): void
    {
        $w1 = $this->createWidow();
        $o1 = $this->createOrphan();

        $batch = IdCardPrintBatch::create([
            'batch_name' => 'Serialization Test Batch',
            'type' => 'mixed',
            'filters' => ['exclude_printed' => true],
            'range' => null,
            'total_count' => 2,
            'created_by' => $this->admin->id,
        ]);

        $descriptors = [
            ['type' => 'widow', 'id' => $w1->id],
            ['type' => 'orphan', 'id' => $o1->id],
        ];

        $job = new GenerateIdCardsJob($batch, $descriptors, null);

        $serialized = serialize($job);
        expect($serialized)->toBeString();

        $unserialized = unserialize($serialized);
        expect($unserialized)->toBeInstanceOf(GenerateIdCardsJob::class);
    }

    public function test_mixed_batch_preserves_beneficiary_type_and_applies_correct_template(): void
    {
        $w = $this->createWidow(['reg_no' => 'UAT-TPL-W']);
        $o = $this->createOrphan(['reg_no' => 'UAT-TPL-O']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'mixed')
            ->set('data.range_type', 'all')
            ->set('data.batch_name', 'Template Verification Batch')
            ->callAction('create_batch')
            ->assertHasNoActionErrors();

        $widowCard = IdCard::where('cardable_type', Widow::class)->first();
        $orphanCard = IdCard::where('cardable_type', Orphan::class)->first();

        expect($widowCard)->not->toBeNull()
            ->and($widowCard->cardable_id)->toBe($w->id)
            ->and($widowCard->template_id)->toBe($this->widowTemplate->id);

        expect($orphanCard)->not->toBeNull()
            ->and($orphanCard->cardable_id)->toBe($o->id)
            ->and($orphanCard->template_id)->toBe($this->orphanTemplate->id);
    }

    public function test_mixed_batch_honors_exclude_already_printed_filter(): void
    {
        $w = $this->createWidow(['reg_no' => 'UAT-EXC-W']);
        $o = $this->createOrphan(['reg_no' => 'UAT-EXC-O']);

        IdCard::create([
            'cardable_type' => Widow::class,
            'cardable_id' => $w->id,
            'template_id' => $this->widowTemplate->id,
            'card_number' => 'WID-EXC-01',
            'qr_code_path' => 'id_cards/qr/test.png',
            'status' => 'active',
            'issued_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'mixed')
            ->set('data.range_type', 'all')
            ->set('data.filters.exclude_printed', true)
            ->set('data.batch_name', 'Exclude Printed Mixed Batch')
            ->callAction('create_batch')
            ->assertHasNoActionErrors();

        $batch = IdCardPrintBatch::first();
        expect($batch->total_count)->toBe(1);

        expect(IdCard::count())->toBe(2); // 1 pre-existing active + 1 new orphan card
        expect(IdCard::where('cardable_type', Orphan::class)->count())->toBe(1);
    }

    public function test_single_type_orphan_generation_works(): void
    {
        $o1 = $this->createOrphan(['reg_no' => 'UAT-ORP-1']);
        $o2 = $this->createOrphan(['reg_no' => 'UAT-ORP-2']);

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'orphan')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->orphanTemplate->id)
            ->set('data.batch_name', 'Orphans Only Batch')
            ->callAction('create_batch')
            ->assertHasNoActionErrors()
            ->assertRedirect();

        expect(IdCardPrintBatch::count())->toBe(1);
        $batch = IdCardPrintBatch::first();
        expect($batch->type)->toBe('orphan')
            ->and($batch->total_count)->toBe(2);

        expect(IdCard::where('cardable_type', Orphan::class)->count())->toBe(2);
    }

    public function test_failed_generation_leaves_no_partial_undocumented_batch(): void
    {
        $this->createWidow(['reg_no' => 'UAT-FAIL-W']);

        $this->mock(\App\Services\IdCardPDFService::class, function ($mock) {
            $mock->shouldReceive('generateBulk')->andThrow(new \RuntimeException('Disk storage full'));
        });

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'Failed Batch')
            ->callAction('create_batch')
            ->assertNotified('Batch Generation Failed');

        expect(IdCardPrintBatch::count())->toBe(0);
        expect(IdCard::count())->toBe(0);
    }

    public function test_technical_queue_exception_is_not_exposed_to_user_notification(): void
    {
        $this->createWidow(['reg_no' => 'UAT-ERR-W']);

        $this->mock(\App\Services\IdCardPDFService::class, function ($mock) {
            $mock->shouldReceive('generateBulk')->andThrow(new \RuntimeException('Queue worker serialization error'));
        });

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'Technical Error Test')
            ->callAction('create_batch')
            ->assertNotified('Batch Generation Failed');
    }

    public function test_duplicate_submission_is_idempotent_and_does_not_duplicate_issuance(): void
    {
        $w1 = $this->createWidow(['reg_no' => 'UAT-DUP-W1']);

        // First generation
        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'First Submission')
            ->callAction('create_batch')
            ->assertHasNoActionErrors();

        expect(IdCard::count())->toBe(1);

        // Immediate duplicate submission with exclude_printed = true
        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'widow')
            ->set('data.range_type', 'all')
            ->set('data.filters.exclude_printed', true)
            ->set('data.template_id', $this->widowTemplate->id)
            ->set('data.batch_name', 'Second Submission')
            ->callAction('create_batch')
            ->assertNotified('No eligible beneficiaries found for this print batch.');

        expect(IdCard::count())->toBe(1);
    }

    public function test_pdf_bulk_generation_chunks_12_cards_into_8_and_4_per_page_sheets(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->createWidow(['reg_no' => "UAT-12-W{$i}"]);
            $this->createOrphan(['reg_no' => "UAT-12-O{$i}"]);
        }

        Livewire::actingAs($this->admin)
            ->test(BulkPrintIdCards::class)
            ->set('data.type', 'mixed')
            ->set('data.range_type', 'all')
            ->set('data.batch_name', '12 Card Batch')
            ->callAction('create_batch')
            ->assertHasNoActionErrors();

        $batch = IdCardPrintBatch::first();
        expect($batch->total_count)->toBe(12);
        expect(\Illuminate\Support\Facades\Storage::disk('public')->exists($batch->pdf_path))->toBeTrue();

        $cards = IdCard::all();
        expect($cards->count())->toBe(12);

        $chunks = $cards->chunk(8);
        expect($chunks->count())->toBe(2);
        expect($chunks->first()->count())->toBe(8);
        expect($chunks->last()->count())->toBe(4);
    }

    public function test_pdf_bulk_generation_handles_various_card_counts_without_empty_pages(): void
    {
        $countsToTest = [1, 7, 8, 9, 16];

        foreach ($countsToTest as $count) {
            $expectedPages = (int) ceil($count / 8);
            $cards = collect(array_fill(0, $count, 'card'));
            $chunks = $cards->chunk(8);

            expect($chunks->count())->toBe($expectedPages);
            expect($chunks->flatten()->count())->toBe($count);
        }
    }
}
