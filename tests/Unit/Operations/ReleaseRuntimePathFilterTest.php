<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waymark\Release\RuntimePathFilter;

require_once dirname(__DIR__, 3).'/scripts/release/RuntimePathFilter.php';

final class ReleaseRuntimePathFilterTest extends TestCase
{
    #[DataProvider('runtimePaths')]
    public function test_it_excludes_runtime_state_generated_during_release_verification(string $path): void
    {
        $this->assertTrue(RuntimePathFilter::excludes($path));
    }

    public static function runtimePaths(): array
    {
        return [
            ['storage/app/private/fallback-last-run.json'],
            ['storage/app/public/published-photo.jpg'],
            ['storage/app/uploads/member-photo.jpg'],
            ['storage/app/backups/site.zip'],
            ['storage/framework/cache/data/cache-entry'],
            ['storage/framework/sessions/session-id'],
            ['storage/framework/views/compiled.php'],
            ['storage/framework/maintenance.json'],
            ['storage/logs/laravel.log'],
        ];
    }

    #[DataProvider('scaffoldingPaths')]
    public function test_it_preserves_required_storage_scaffolding(string $path): void
    {
        $this->assertFalse(RuntimePathFilter::excludes($path));
    }

    public static function scaffoldingPaths(): array
    {
        return [
            ['storage/app/.gitignore'],
            ['storage/app/private/.gitignore'],
            ['storage/app/public/.gitignore'],
            ['storage/framework/cache/.gitignore'],
            ['storage/framework/sessions/.gitignore'],
            ['storage/framework/views/.gitignore'],
            ['storage/logs/.gitignore'],
        ];
    }
}
