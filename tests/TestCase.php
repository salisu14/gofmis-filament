<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fail fast if the resolved test database points at the development
     * SQLite file. Protects database/database.sqlite from being reset by
     * RefreshDatabase/migrations when test isolation is misconfigured.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTestDatabaseIsIsolated();
    }

    protected function assertTestDatabaseIsIsolated(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $database = (string) config('database.connections.sqlite.database');

        if ($database === ':memory:') {
            return;
        }

        $devDatabase = database_path('database.sqlite');

        if ($database === $devDatabase || realpath($database) === realpath($devDatabase)) {
            throw new \RuntimeException(
                'Test database safety violation: automated tests cannot use the development database (database/database.sqlite). '.
                'Ensure phpunit.xml forces DB_DATABASE=:memory: (or a dedicated testing.sqlite file).'
            );
        }
    }
}
