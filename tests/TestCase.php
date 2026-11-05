<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable Vite manifest check in testing environment
        // This prevents "Vite manifest not found" errors during tests
        $this->withoutVite();
    }
}
