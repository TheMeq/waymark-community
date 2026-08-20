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

    public function test_dependency_resolution_targets_the_minimum_supported_php_runtime(): void
    {
        $composer = json_decode(
            file_get_contents(base_path('composer.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $lock = json_decode(
            file_get_contents(base_path('composer.lock')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('^8.3', $composer['require']['php']);
        $this->assertSame('8.3.0', $composer['config']['platform']['php']);
        $this->assertSame('8.3.0', $lock['platform-overrides']['php']);
    }
}
