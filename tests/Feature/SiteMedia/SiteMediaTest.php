<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageMetadata;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\SiteMedia\Actions\DeleteSiteMedia;
use App\Domain\SiteMedia\Actions\MarkSiteMediaForRepair;
use App\Domain\SiteMedia\Actions\PromoteCommunityPhotoToSiteMedia;
use App\Domain\SiteMedia\Actions\RegenerateSiteMedia;
use App\Domain\SiteMedia\Actions\SiteMediaNamespaceCleaner;
use App\Domain\SiteMedia\Actions\UpdateSiteMediaMetadata;
use App\Domain\SiteMedia\Actions\UploadSiteMedia;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Filament\Pages\SiteMediaLibrary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class SiteMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_media_requires_meaningful_alt_text_unless_decorative(): void
    {
        $this->expectException(\LogicException::class);

        SiteMedia::query()->create($this->attributes(['alt_text' => null, 'is_decorative' => false]));
    }

    public function test_promoting_an_approved_safe_photo_copies_its_derivative_into_the_site_media_namespace(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());

        $media = app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata('Hill walkers', false, 0.3, 0.7));

        $this->assertSame($photo->id, $media->source_community_photo_id);
        $this->assertStringStartsWith('site-media/', $media->processed_variants['master']);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
        $this->assertTrue(Storage::disk('local')->exists($photo->processed_variants['master']));
    }

    public function test_metadata_update_enforces_focal_bounds_and_records_an_audit(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());

        app(UpdateSiteMediaMetadata::class)->handle($actor, $media, new SiteMediaMetadata('A bright ridge', false, 0.25, 0.75));

        $this->assertSame('A bright ridge', $media->fresh()->alt_text);
        $this->assertSame('metadata_updated', $media->audits()->sole()->action);
        $this->expectException(ValidationException::class);
        app(UpdateSiteMediaMetadata::class)->handle($actor, $media, new SiteMediaMetadata('A bright ridge', false, 1.1, 0.5));
    }

    public function test_promoted_copy_survives_source_photo_deletion(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());
        $media = app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata('Hill walkers', false));

        $photo->delete();

        $this->assertNull($media->fresh()->source_community_photo_id);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
    }

    public function test_public_stream_never_exposes_storage_paths_and_returns_no_store_response(): void
    {
        Storage::fake('local');
        $media = SiteMedia::query()->create($this->attributes());
        Storage::disk('local')->put($media->processed_variants['master'], 'safe image');

        $this->get(route('site-media.stream', [$media, 'master']))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertDontSee('site-media/', false);
    }

    public function test_public_stream_returns_not_found_for_unsafe_or_missing_variants(): void
    {
        Storage::fake('local');
        $media = SiteMedia::query()->create($this->attributes(['processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg']]));

        $this->get(route('site-media.stream', [$media, 'master']))->assertNotFound();
    }

    public function test_safe_upload_creates_only_site_media_derivatives(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);

        $media = app(UploadSiteMedia::class)->handle($actor, UploadedFile::fake()->image('ridge.jpg', 1200, 800), new SiteMediaMetadata('Walkers on a ridge', false));

        $this->assertStringStartsWith('site-media/', $media->processed_variants['master']);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
        $this->assertSame(0, CommunityPhoto::query()->count());
        $this->assertSame('uploaded', $media->audits()->sole()->action);
    }

    public function test_upload_cleans_processed_derivatives_and_records_no_state_when_persistence_fails(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        SiteMedia::creating(static function (): void {
            throw new \RuntimeException('Database persistence failed.');
        });

        try {
            app(UploadSiteMedia::class)->handle($actor, UploadedFile::fake()->image('ridge.jpg', 1200, 800), new SiteMediaMetadata('Walkers on a ridge', false));
            $this->fail('The upload persisted despite the simulated database failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Database persistence failed.', $exception->getMessage());
        } finally {
            SiteMedia::flushEventListeners();
            SiteMedia::clearBootedModels();
        }

        $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertDatabaseCount('community_photos', 0);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_upload_cleans_partial_processing_output_when_a_later_derivative_fails(): void
    {
        Storage::fake('local');
        $this->app->bind(RasterImageTransformer::class, fn (): RasterImageTransformer => new class implements RasterImageTransformer
        {
            public function supportsInput(string $mimeType): bool
            {
                return $mimeType === 'image/png';
            }

            public function supportsOutput(string $mimeType): bool
            {
                return $mimeType === 'image/jpeg';
            }

            public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
            {
                return new class implements DecodedRasterImage
                {
                    public function width(): int
                    {
                        return 1200;
                    }

                    public function height(): int
                    {
                        return 800;
                    }

                    public function release(): void {}
                };
            }

            public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
            {
                if ($variant->name !== 'master') {
                    throw new \RuntimeException('A later derivative failed.');
                }

                return new TransformedRasterImage('master-only', 1200, 800, 'image/jpeg');
            }
        });
        $this->app->bind(ImageMetadataReader::class, fn (): ImageMetadataReader => new class implements ImageMetadataReader
        {
            public function read(string $path, string $mimeType): ImageMetadata
            {
                return new ImageMetadata(1, null);
            }
        });
        $actor = User::factory()->create(['is_admin' => true]);

        try {
            app(UploadSiteMedia::class)->handle($actor, UploadedFile::fake()->image('ridge.png', 1200, 800), new SiteMediaMetadata('Walkers on a ridge', false));
            $this->fail('The partially processed upload unexpectedly completed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('A later derivative failed.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertDatabaseCount('community_photos', 0);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_direct_model_save_rejects_out_of_bounds_focal_point(): void
    {
        $this->expectException(\LogicException::class);
        SiteMedia::query()->create($this->attributes(['focal_point_x' => 1.01]));
    }

    public function test_database_enforces_alt_or_decorative_and_focal_point_invariants_when_eloquent_is_bypassed(): void
    {
        $attributes = $this->attributes();
        unset($attributes['processed_variants']);
        $attributes['processed_variants'] = json_encode(['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'], JSON_THROW_ON_ERROR);
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        $this->expectException(QueryException::class);
        DB::table('site_media')->insert(array_replace($attributes, ['alt_text' => null, 'is_decorative' => false]));
    }

    public function test_database_enforces_focal_bounds_when_eloquent_is_bypassed(): void
    {
        $attributes = $this->attributes();
        unset($attributes['processed_variants']);
        $attributes['processed_variants'] = json_encode(['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'], JSON_THROW_ON_ERROR);
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        $this->expectException(QueryException::class);
        DB::table('site_media')->insert(array_replace($attributes, ['focal_point_y' => -0.0001]));
    }

    public function test_database_allows_decorative_media_and_boundary_focal_points_when_eloquent_is_bypassed(): void
    {
        $attributes = $this->attributes();
        unset($attributes['processed_variants']);
        $attributes['processed_variants'] = json_encode(['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'], JSON_THROW_ON_ERROR);
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        DB::table('site_media')->insert(array_replace($attributes, ['alt_text' => null, 'is_decorative' => true, 'focal_point_x' => 0, 'focal_point_y' => 1]));

        $this->assertDatabaseHas('site_media', ['alt_text' => null, 'is_decorative' => true, 'focal_point_x' => 0, 'focal_point_y' => 1]);
    }

    public function test_site_media_schema_has_the_expected_foreign_keys_and_additive_repair_columns(): void
    {
        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('site_media')"))->map(fn (object $key): array => [(string) $key->from, (string) $key->table, (string) $key->on_delete])->all();

        $this->assertContains(['created_by_user_id', 'users', 'RESTRICT'], $foreignKeys);
        $this->assertContains(['source_community_photo_id', 'community_photos', 'SET NULL'], $foreignKeys);
        $this->assertTrue(Schema::hasColumns('site_media', ['regeneration_cleanup_status', 'regeneration_cleanup_storage_disk', 'regeneration_cleanup_storage_key']));
        $this->assertTrue(Schema::hasTable('site_media_audits'));
    }

    public function test_site_media_migrations_apply_and_reverse_cleanly_on_an_isolated_sqlite_database(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'waymark-site-media-schema-');
        $connections = config('database.connections');
        $default = config('database.default');
        $connection = 'site_media_schema_test';
        config()->set('database.default', $connection);
        config()->set("database.connections.{$connection}", ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true]);

        try {
            DB::purge($connection);
            $create = require base_path('database/migrations/2026_08_22_100000_create_site_media_tables.php');
            $focal = require base_path('database/migrations/2026_08_22_101000_harden_site_media_focal_points.php');
            $cleanup = require base_path('database/migrations/2026_08_22_102000_add_regeneration_cleanup_to_site_media.php');
            $create->up();
            $focal->up();
            $cleanup->up();

            $schema = Schema::connection($connection);
            $this->assertTrue($schema->hasColumns('site_media', ['regeneration_cleanup_status', 'regeneration_cleanup_storage_disk', 'regeneration_cleanup_storage_key']));
            $this->assertTrue($schema->hasTable('site_media_audits'));

            $cleanup->down();
            $focal->down();
            $create->down();
            $this->assertFalse($schema->hasTable('site_media'));
            $this->assertFalse($schema->hasTable('site_media_audits'));
        } finally {
            DB::purge($connection);
            config()->set('database.connections', $connections);
            config()->set('database.default', $default);
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_metadata_focal_edits_are_audited_but_normalised_noops_are_not(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());

        app(UpdateSiteMediaMetadata::class)->handle($actor, $media, new SiteMediaMetadata('Walkers on a ridge', false, 0.625, 0.375));
        app(UpdateSiteMediaMetadata::class)->handle($actor, $media->fresh(), new SiteMediaMetadata('Walkers on a ridge', false, 0.62504, 0.37496));

        $this->assertSame('0.6250', $media->fresh()->focal_point_x);
        $this->assertSame('0.3750', $media->fresh()->focal_point_y);
        $this->assertSame(['metadata_updated'], $media->audits()->pluck('action')->all());
    }

    public function test_promotion_rejects_every_ineligible_source_without_changing_source_or_creating_site_side_effects(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);

        foreach ([
            ['moderation_status' => 'pending'],
            ['moderation_status' => 'rejected'],
            ['published_at' => now()->addMinute()],
            ['published_at' => null],
            ['processing_status' => 'processing'],
            ['processed_variants' => ['master' => '../.env']],
        ] as $overrides) {
            $photo = $this->approvedPhoto($actor);
            $photo->forceFill($overrides)->saveQuietly();
            if (($overrides['processed_variants']['master'] ?? null) !== '../.env') {
                Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());
            }
            $before = $photo->fresh()->getAttributes();

            try {
                app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata('Hill walkers', false));
                $this->fail('An ineligible community photo was promoted.');
            } catch (ValidationException) {
                // Expected: no source or site-media state may change.
            }

            $this->assertSame($before, $photo->fresh()->getAttributes());
        }

        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_unauthorised_promotion_leaves_the_source_and_all_side_effect_tables_unchanged(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($owner);
        Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());
        $before = $photo->fresh()->getAttributes();

        $this->expectException(AuthorizationException::class);
        try {
            app(PromoteCommunityPhotoToSiteMedia::class)->handle(User::factory()->create(), $photo, new SiteMediaMetadata('Hill walkers', false));
        } finally {
            $this->assertSame($before, $photo->fresh()->getAttributes());
            $this->assertDatabaseCount('site_media', 0);
            $this->assertDatabaseCount('site_media_audits', 0);
            $this->assertDatabaseCount('community_photo_moderation_audits', 0);
        }
    }

    public function test_livewire_promotion_rejects_a_forged_photo_id_without_creating_media_or_audits(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);

        try {
            Livewire::actingAs($actor)->test(SiteMediaLibrary::class)->call('promote', 999999);
            $this->fail('A forged community photo identifier was accepted.');
        } catch (ModelNotFoundException) {
            // The page resolves the id server-side; no client-supplied source is trusted.
        }

        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_repair_action_marks_missing_derivatives_and_is_idempotently_audited(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());

        app(MarkSiteMediaForRepair::class)->handle($actor, $media);
        app(MarkSiteMediaForRepair::class)->handle($actor, $media);

        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertSame(['repair_required'], $media->audits()->pluck('action')->all());
    }

    public function test_repair_action_marks_an_empty_variant_set_for_repair(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes(['processed_variants' => []]));

        app(MarkSiteMediaForRepair::class)->handle($actor, $media);

        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertSame('repair_required', $media->audits()->sole()->action);
    }

    public function test_repair_action_requires_a_safe_existing_master_variant(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $thumbnail = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/thumbnail.jpg';
        Storage::disk('local')->put($thumbnail, 'safe thumbnail');
        $media = SiteMedia::query()->create($this->attributes(['processed_variants' => ['thumbnail' => $thumbnail]]));

        app(MarkSiteMediaForRepair::class)->handle($actor, $media);

        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertSame('repair_required', $media->audits()->sole()->action);
    }

    public function test_repair_action_marks_unsafe_derivatives_without_attempting_to_expose_or_replace_them(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());
        DB::table('site_media')->whereKey($media->id)->update(['processed_variants' => json_encode(['master' => '../.env'], JSON_THROW_ON_ERROR)]);

        app(MarkSiteMediaForRepair::class)->handle($actor, $media->fresh());

        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertSame(['repair_required'], $media->audits()->pluck('action')->all());
    }

    public function test_unprivileged_user_cannot_promote_or_edit_site_media(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UpdateSiteMediaMetadata::class)->handle(User::factory()->create(), SiteMedia::query()->create($this->attributes()), new SiteMediaMetadata('No access', false));
    }

    public function test_regeneration_retains_old_namespace_for_durable_retry_when_cleanup_fails(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        $media = SiteMedia::query()->create($this->attributes(['source_community_photo_id' => $photo->id]));
        $replacement = SiteMedia::query()->create($this->attributes([
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3311',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3311/master.jpg'],
        ]));
        $promotion = Mockery::mock(PromoteCommunityPhotoToSiteMedia::class);
        $promotion->shouldReceive('handle')->once()->andReturn($replacement);
        $cleaner = Mockery::mock(SiteMediaNamespaceCleaner::class);
        $cleaner->shouldReceive('delete')->once()->with('local', $media->storage_key)->andReturnFalse();

        app(RegenerateSiteMedia::class, ['promotion' => $promotion, 'cleaner' => $cleaner])->handle($actor, $media);

        $media->refresh();
        $this->assertSame($replacement->storage_key, $media->storage_key);
        $this->assertSame('failed', $media->regeneration_cleanup_status);
        $this->assertSame('3f2504e0-4f89-41d3-9a0c-0305e82c3300', $media->regeneration_cleanup_storage_key);
        $this->assertFalse(SiteMedia::query()->whereKey($replacement->id)->exists());
        $this->assertSame(['regenerated', 'regeneration_cleanup_failed'], $media->audits()->pluck('action')->all());
    }

    public function test_regeneration_cleanup_retry_only_removes_the_retained_old_namespace(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes([
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3311',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3311/master.jpg'],
            'regeneration_cleanup_status' => 'failed',
            'regeneration_cleanup_storage_disk' => 'local',
            'regeneration_cleanup_storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
        ]));
        $oldPath = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';
        $newPath = $media->processed_variants['master'];
        Storage::disk('local')->put($oldPath, 'old');
        Storage::disk('local')->put($newPath, 'new');

        $this->assertTrue(app(RegenerateSiteMedia::class)->retryCleanup($actor, $media));

        $media->refresh();
        $this->assertNull($media->regeneration_cleanup_status);
        $this->assertNull($media->regeneration_cleanup_storage_key);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertSame('regeneration_cleanup_completed', $media->audits()->latest('id')->value('action'));
    }

    public function test_failed_removal_retains_the_record_for_a_later_safe_retry(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());
        $disk = Mockery::mock();
        $disk->shouldReceive('deleteDirectory')->once()->with('site-media/'.$media->storage_key)->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->assertFalse(app(DeleteSiteMedia::class)->handle($actor, $media));
        $this->assertSame('deletion_failed', $media->fresh()->health_status);

        $this->app->forgetInstance('filesystem');
        Storage::clearResolvedInstance('filesystem');
        Storage::fake('local');
        Storage::disk('local')->put('site-media/'.$media->storage_key.'/master.jpg', 'safe image');

        $this->assertTrue(app(DeleteSiteMedia::class)->retry($actor, $media->fresh()));
        $this->assertSame('removed', $media->fresh()->health_status);
        $this->assertSame(['removal_requested', 'deletion_failed', 'removed'], $media->audits()->pluck('action')->all());
    }

    /** @return array<string, mixed> */
    private function attributes(array $overrides = []): array
    {
        return array_replace([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_disk' => 'local',
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'mime_type' => 'image/jpeg', 'width' => 1600, 'height' => 1000, 'file_size_bytes' => 1234,
            'alt_text' => 'Walkers on a ridge', 'is_decorative' => false,
            'focal_point_x' => 0.5, 'focal_point_y' => 0.5, 'processing_status' => 'complete', 'health_status' => 'healthy',
        ], $overrides);
    }

    private function approvedPhoto(User $actor): CommunityPhoto
    {
        $event = Event::factory()->create();

        return CommunityPhoto::query()->create([
            'event_id' => $event->id, 'uploader_id' => $actor->id, 'media_type' => 'image', 'processing_status' => 'complete',
            'storage_disk' => 'local', 'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg',
            'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'moderation_status' => 'approved', 'published_at' => now()->subMinute(),
        ]);
    }

    private function safeRaster(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', true);
    }
}
