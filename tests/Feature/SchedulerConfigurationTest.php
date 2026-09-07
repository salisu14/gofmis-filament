<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = app(Schedule::class);
    }

    public function test_scheduler_boots_cleanly(): void
    {
        $this->assertInstanceOf(Schedule::class, $this->schedule);
    }

    public function test_intended_safe_commands_are_scheduled_with_expected_cadence_and_overlap_protection(): void
    {
        $events = collect($this->schedule->events());

        $expectedCommands = [
            'widow-loans:evaluate-delinquency' => ['expression' => '0 0 * * *', 'without_overlapping' => true],
            'finance:reconcile' => ['expression' => '0 1 * * *', 'without_overlapping' => true],
            'inventory:reconcile' => ['expression' => '30 1 * * *', 'without_overlapping' => true],
            'widow-loans:reconcile' => ['expression' => '0 2 * * *', 'without_overlapping' => true],
            'id-cards:reconcile' => ['expression' => '30 2 * * 0', 'without_overlapping' => true],
            'security:rbac-audit' => ['expression' => '0 3 * * 0', 'without_overlapping' => true],
            'zone-coordinators:reconcile' => ['expression' => '0 4 1 * *', 'without_overlapping' => true],
        ];

        foreach ($expectedCommands as $signature => $expectations) {
            /** @var Event|null $event */
            $event = $events->first(fn (Event $e) => str_contains($e->command ?? '', $signature));

            $this->assertNotNull($event, "Scheduled command [{$signature}] was not found in scheduler.");
            $this->assertEquals($expectations['expression'], $event->expression, "Cadence expression mismatch for [{$signature}].");
            $this->assertTrue($event->withoutOverlapping, "Overlap protection (withoutOverlapping) is missing for [{$signature}].");
        }
    }

    public function test_mutating_repair_commands_are_strictly_excluded_from_scheduler(): void
    {
        $events = collect($this->schedule->events());

        $excludedCommands = [
            'finance:repair-bank-balances',
            'zone-coordinators:backfill',
            'finance:fix-transaction-morphs',
            'gofmis:provision-demo-observer',
            'biometrics:reencrypt',
        ];

        foreach ($excludedCommands as $signature) {
            $event = $events->first(fn (Event $e) => str_contains($e->command ?? '', $signature));

            $this->assertNull($event, "Mutating/destructive repair command [{$signature}] MUST NOT be scheduled unattended.");
        }
    }
}
