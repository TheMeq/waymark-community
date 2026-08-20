<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Application;
use Tests\TestCase;

final class RuntimeRequirementsTest extends TestCase
{
    public function test_supported_php_and_laravel_runtime_is_in_use(): void
    {
        $this->assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
        $this->assertSame('13', explode('.', Application::VERSION)[0]);
    }
}
