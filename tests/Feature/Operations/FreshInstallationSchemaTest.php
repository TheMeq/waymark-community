<?php

namespace Tests\Feature\Operations;

use App\Domain\Events\Models\Event;
use App\Domain\Operations\Installation\FreshInstallationSchema;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_managed_media_schema_has_nullable_owner_references_and_cleanup_index(): void
    {
        self::assertTrue(Schema::hasColumns('site_media', ['purpose', 'orphaned_at']));
        self::assertTrue(Schema::hasColumns('walks', ['featured_image_media_id', 'featured_image_alt_text']));
        self::assertTrue(Schema::hasColumns('site_profiles', ['logo_media_id', 'favicon_media_id']));

        $siteMediaColumns = collect(Schema::getColumns('site_media'))->keyBy('name');
        $walkColumns = collect(Schema::getColumns('walks'))->keyBy('name');
        $siteProfileColumns = collect(Schema::getColumns('site_profiles'))->keyBy('name');

        self::assertFalse($siteMediaColumns['purpose']['nullable']);
        self::assertTrue($siteMediaColumns['orphaned_at']['nullable']);
        self::assertTrue($walkColumns['featured_image_media_id']['nullable']);
        self::assertTrue($walkColumns['featured_image_alt_text']['nullable']);
        self::assertSame('text', Schema::getColumnType('walks', 'featured_image_alt_text'));
        self::assertTrue($siteProfileColumns['logo_media_id']['nullable']);
        self::assertTrue($siteProfileColumns['favicon_media_id']['nullable']);

        $cleanupIndex = collect(Schema::getIndexes('site_media'))
            ->first(fn (array $index): bool => $index['columns'] === ['purpose', 'orphaned_at']);
        self::assertNotNull($cleanupIndex);

        $walkForeignKeys = collect(Schema::getForeignKeys('walks'));
        $profileForeignKeys = collect(Schema::getForeignKeys('site_profiles'));

        self::assertTrue($walkForeignKeys->contains(fn (array $key): bool => $this->isNullableSiteMediaReference($key, 'featured_image_media_id')));
        self::assertTrue($profileForeignKeys->contains(fn (array $key): bool => $this->isNullableSiteMediaReference($key, 'logo_media_id')));
        self::assertTrue($profileForeignKeys->contains(fn (array $key): bool => $this->isNullableSiteMediaReference($key, 'favicon_media_id')));
    }

    public function test_fallback_columns_are_declared_for_2048_character_values(): void
    {
        self::assertFileExists(database_path('migrations/2026_09_09_100000_add_managed_site_and_walk_media.php'));
        $lengths = [];

        Schema::shouldReceive('table')
            ->times(3)
            ->andReturnUsing(function (string $table, callable $callback) use (&$lengths): void {
                $blueprint = new Blueprint(DB::connection(), $table);
                $callback($blueprint);

                foreach ($blueprint->getColumns() as $column) {
                    if (in_array($column->name, ['featured_image_path', 'logo_path', 'favicon_path'], true)) {
                        $lengths[$table.'.'.$column->name] = $column->length;
                    }
                }
            });

        $this->managedMediaMigration()->up();

        self::assertSame([
            'walks.featured_image_path' => 2048,
            'site_profiles.logo_path' => 2048,
            'site_profiles.favicon_path' => 2048,
        ], $lengths);
    }

    public function test_forward_and_reverse_migration_preserve_existing_fallback_values_without_lossy_contraction(): void
    {
        self::assertFileExists(database_path('migrations/2026_09_09_100000_add_managed_site_and_walk_media.php'));

        $actor = User::factory()->create();
        $media = SiteMedia::query()->create([
            'created_by_user_id' => $actor->id,
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
            'storage_disk' => 'local',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 1000,
            'file_size_bytes' => 1234,
            'alt_text' => 'Walkers on a ridge',
            'is_decorative' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
        ]);
        $event = Event::factory()->for($actor, 'organiser')->create();
        $longValue = '/images/'.str_repeat('a', 2040);
        self::assertSame(2048, strlen($longValue));
        $walk = Walk::query()->create([
            'event_id' => $event->id,
            'primary_leader_id' => $actor->id,
            'featured_image_path' => $longValue,
        ]);
        $profile = SiteProfile::query()->create([
            'group_name' => 'Peak Pathfinders',
            'logo_path' => $longValue,
            'favicon_path' => $longValue,
        ]);

        $migration = $this->managedMediaMigration();
        $migration->down();

        self::assertFalse(Schema::hasColumn('site_media', 'purpose'));
        self::assertFalse(Schema::hasColumn('walks', 'featured_image_media_id'));
        self::assertFalse(Schema::hasColumn('site_profiles', 'logo_media_id'));
        self::assertSame($longValue, DB::table('walks')->where('id', $walk->id)->value('featured_image_path'));
        self::assertSame($longValue, DB::table('site_profiles')->where('id', $profile->id)->value('logo_path'));
        self::assertSame($longValue, DB::table('site_profiles')->where('id', $profile->id)->value('favicon_path'));

        $migration->up();

        self::assertSame('library', DB::table('site_media')->where('id', $media->id)->value('purpose'));
        self::assertSame($longValue, DB::table('walks')->where('id', $walk->id)->value('featured_image_path'));
        self::assertSame($longValue, DB::table('site_profiles')->where('id', $profile->id)->value('logo_path'));
        self::assertSame($longValue, DB::table('site_profiles')->where('id', $profile->id)->value('favicon_path'));
    }

    private function managedMediaMigration(): Migration
    {
        return require database_path('migrations/2026_09_09_100000_add_managed_site_and_walk_media.php');
    }

    /** @param array<string, mixed> $key */
    private function isNullableSiteMediaReference(array $key, string $column): bool
    {
        return $key['columns'] === [$column]
            && $key['foreign_table'] === 'site_media'
            && strtoupper((string) $key['on_delete']) === 'SET NULL';
    }
}
