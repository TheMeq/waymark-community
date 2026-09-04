<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\FreshInstallationSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FreshInstallationSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_generated_manifest_matches_authoritative_migrations_and_the_migrated_schema(): void
    {
        self::assertFileExists(resource_path('installation/fresh-schema.json'));

        $manifest = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'));
        $migrationNames = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(database_path('migrations/*.php')) ?: [],
        );
        sort($migrationNames);

        self::assertSame($migrationNames, $manifest->migrationNames());
        self::assertSame(hash('sha256', implode("\n", $migrationNames)), $manifest->migrationSetHash());
        self::assertSame(FreshInstallationSchema::capture(DB::connection())->tables(), $manifest->tables());
    }
}
