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

    public function test_it_purges_runtime_files_created_by_production_dependency_discovery(): void
    {
        $root = sys_get_temp_dir().'/waymark-release-runtime-'.bin2hex(random_bytes(8));
        mkdir($root.'/storage/app/private', 0700, true);
        mkdir($root.'/storage/framework/cache', 0700, true);
        file_put_contents($root.'/storage/app/private/.gitignore', "*\n!.gitignore\n");
        file_put_contents($root.'/storage/app/private/setup.key', 'ephemeral');
        file_put_contents($root.'/storage/framework/cache/packages.php', '<?php return [];');

        try {
            $this->assertSame(2, RuntimePathFilter::purge($root));
            $this->assertFileExists($root.'/storage/app/private/.gitignore');
            $this->assertFileDoesNotExist($root.'/storage/app/private/setup.key');
            $this->assertFileDoesNotExist($root.'/storage/framework/cache/packages.php');
        } finally {
            @unlink($root.'/storage/app/private/setup.key');
            @unlink($root.'/storage/app/private/.gitignore');
            @unlink($root.'/storage/framework/cache/packages.php');
            @rmdir($root.'/storage/app/private');
            @rmdir($root.'/storage/app');
            @rmdir($root.'/storage/framework/cache');
            @rmdir($root.'/storage/framework');
            @rmdir($root.'/storage');
            @rmdir($root);
        }
    }
}
