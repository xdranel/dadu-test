<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dadu_compiled_views';
        if (!is_dir($compiledPath)) {
            @mkdir($compiledPath, 0777, true);
        }
        config(['view.compiled' => $compiledPath]);
        
        // Disable Vite manifest check in testing environment
        // This prevents "Vite manifest not found" errors during tests
        $this->withoutVite();
    }
}
