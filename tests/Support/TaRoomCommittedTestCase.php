<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\TestCase;

abstract class TaRoomCommittedTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_DATABASE') !== 'dadu_testing') {
            $this->markTestSkipped('Requires explicit dedicated MySQL dadu_testing; no application DB fallback.');
        }
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        // Wipe only the explicitly guarded test DB. Avoid unrelated legacy down() failures.
        $this->beforeApplicationDestroyed(function () {
            $this->artisan('db:wipe', ['--force' => true])->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
    }
}
