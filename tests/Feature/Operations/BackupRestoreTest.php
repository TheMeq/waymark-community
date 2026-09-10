<?php

namespace Tests\Feature\Operations;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Backups\Contracts\RestoreHealthProbe;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    private string $environmentPath;

    private string $installationPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/restore-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:original-key\nAPP_URL=https://source.example\nDB_HOST=source-db\nFILESYSTEM_DISK=source-disk\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.maintenance.state_path', $this->environmentPath.'.maintenance');
        $this->installationPath = $this->environmentPath.'.installed';
        config()->set('waymark.installation.lock_path', $this->installationPath);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        @unlink($this->environmentPath.'.maintenance');
        @unlink($this->installationPath);
        parent::tearDown();
    }

    public function test_verified_backup_restores_database_private_files_and_configuration(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Original Ramblers']);
        Storage::disk('local')->put('community-photos/original.jpg', 'original-image');
        $backup = app(CreateBackup::class)->handle('manual', 'portable passphrase');

        $profile->update(['group_name' => 'Changed Group']);
        Storage::disk('local')->delete('community-photos/original.jpg');
        Storage::disk('local')->put('community-photos/new.jpg', 'new-image');
        file_put_contents($this->environmentPath, "APP_KEY=base64:changed-key\nAPP_URL=https://target.example\nDB_HOST=target-db\nFILESYSTEM_DISK=local\n");

        $safety = app(CreateBackup::class)->handle('pre-restore');
        app(RestoreBackup::class)->fromRunWithSafetyBackup($backup, $safety, 'RESTORE WAYMARK', 'portable passphrase');

        $this->assertSame('Original Ramblers', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('community-photos/original.jpg');
        Storage::disk('local')->assertMissing('community-photos/new.jpg');
        $restoredEnvironment = (string) file_get_contents($this->environmentPath);
        $this->assertStringContainsString('APP_KEY=base64:original-key', $restoredEnvironment);
        $this->assertStringContainsString('APP_URL=https://target.example', $restoredEnvironment);
        $this->assertStringContainsString('DB_HOST=target-db', $restoredEnvironment);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $restoredEnvironment);
        $this->assertFalse(app(MaintenanceManager::class)->active());
    }

    public function test_managed_site_and_walk_media_are_restored_with_owners_fallbacks_and_private_variants(): void
    {
        $creator = User::factory()->create();
        [$walkMedia, $walkFiles] = $this->managedMedia($creator, SiteMediaPurpose::WalkFeaturedImage, 'image/jpeg', 'Walk media description');
        [$logoMedia, $logoFiles] = $this->managedMedia($creator, SiteMediaPurpose::SiteLogo, 'image/png');
        [$faviconMedia, $faviconFiles] = $this->managedMedia($creator, SiteMediaPurpose::SiteFavicon, 'image/png');
        $allMediaFiles = [...$walkFiles, ...$logoFiles, ...$faviconFiles];

        $event = Event::factory()->create([
            'type' => EventType::Walk,
            'title' => 'Restored ridge walk',
            'slug' => 'restored-ridge-walk',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
            'organiser_id' => $creator->id,
        ]);
        $walk = Walk::query()->create([
            'event_id' => $event->id,
            'primary_leader_id' => $creator->id,
            'featured_image_media_id' => $walkMedia->id,
            'featured_image_alt_text' => 'Walkers crossing the restored ridge',
            'featured_image_path' => 'https://images.example.org/walk-fallback.jpg',
        ]);
        $profile = SiteProfile::query()->create([
            'group_name' => 'Restored Walking Group',
            'logo_media_id' => $logoMedia->id,
            'logo_path' => 'https://images.example.org/logo-fallback.png',
            'favicon_media_id' => $faviconMedia->id,
            'favicon_path' => 'https://images.example.org/favicon-fallback.png',
        ]);
        Storage::disk('local')->put('backups/excluded-from-waymark-backup.zip', 'not a backup component');

        $backup = app(CreateBackup::class)->handle('manual');
        $archivePath = Storage::disk('backups')->path($backup->storage_path);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $manifest = json_decode((string) $archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $archive->close();
        $manifestComponents = collect($manifest['components'])->keyBy('path');
        foreach ($allMediaFiles as $path => $contents) {
            $component = $manifestComponents->get('private/'.$path);
            $this->assertIsArray($component);
            $this->assertSame(hash('sha256', $contents), $component['sha256']);
        }
        $this->assertFalse($manifestComponents->has('private/backups/excluded-from-waymark-backup.zip'));

        $walk->forceFill([
            'featured_image_media_id' => null,
            'featured_image_alt_text' => 'Changed description',
            'featured_image_path' => null,
        ])->save();
        $profile->forceFill([
            'logo_media_id' => null,
            'logo_path' => null,
            'favicon_media_id' => null,
            'favicon_path' => null,
        ])->save();
        SiteMedia::query()->delete();
        Storage::disk('local')->delete(Storage::disk('local')->allFiles());
        Storage::disk('local')->put('site-media/unrestored/private-file.png', 'remove during restore');

        $safety = app(CreateBackup::class)->handle('pre-restore');
        app(RestoreBackup::class)->fromRunWithSafetyBackup($backup, $safety, 'RESTORE WAYMARK');

        $restoredWalk = Walk::query()->findOrFail($walk->id);
        $restoredProfile = SiteProfile::query()->findOrFail($profile->id);
        $restoredMedia = SiteMedia::query()->whereIn('id', [$walkMedia->id, $logoMedia->id, $faviconMedia->id])->get()->keyBy('id');

        $this->assertSame($walkMedia->id, $restoredWalk->featured_image_media_id);
        $this->assertSame('Walkers crossing the restored ridge', $restoredWalk->featured_image_alt_text);
        $this->assertSame('https://images.example.org/walk-fallback.jpg', $restoredWalk->featured_image_path);
        $this->assertSame($logoMedia->id, $restoredProfile->logo_media_id);
        $this->assertSame('https://images.example.org/logo-fallback.png', $restoredProfile->logo_path);
        $this->assertSame($faviconMedia->id, $restoredProfile->favicon_media_id);
        $this->assertSame('https://images.example.org/favicon-fallback.png', $restoredProfile->favicon_path);
        $this->assertSame(SiteMediaPurpose::WalkFeaturedImage, $restoredMedia[$walkMedia->id]->purpose);
        $this->assertSame(SiteMediaPurpose::SiteLogo, $restoredMedia[$logoMedia->id]->purpose);
        $this->assertSame(SiteMediaPurpose::SiteFavicon, $restoredMedia[$faviconMedia->id]->purpose);
        $this->assertNull($restoredMedia[$walkMedia->id]->orphaned_at);
        $this->assertNull($restoredMedia[$logoMedia->id]->orphaned_at);
        $this->assertNull($restoredMedia[$faviconMedia->id]->orphaned_at);
        foreach (array_keys($allMediaFiles) as $path) {
            Storage::disk('local')->assertExists($path);
        }
        Storage::disk('local')->assertMissing('site-media/unrestored/private-file.png');

        Cache::flush();
        $walkUrl = route('site-media.stream', [$walkMedia->id, 'large']);
        $logoUrl = route('site-media.stream', [$logoMedia->id, 'medium']);
        $faviconUrl = route('site-media.stream', [$faviconMedia->id, 'favicon']);
        $this->get('/walks/restored-ridge-walk')
            ->assertOk()
            ->assertSee($walkUrl, false)
            ->assertDontSee($walkMedia->storage_key);
        $this->get('/')
            ->assertOk()
            ->assertSee($logoUrl, false)
            ->assertSee($faviconUrl, false)
            ->assertDontSee($logoMedia->storage_key)
            ->assertDontSee($faviconMedia->storage_key);
        $this->get($walkUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($logoUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($faviconUrl)->assertOk()->assertHeader('Content-Type', 'image/png');

        $restoredFavicon = SiteMedia::query()->findOrFail($faviconMedia->id);
        Storage::disk('local')->delete($restoredFavicon->processed_variants['favicon']);
        $this->assertNull(app(SiteMediaPresenter::class)->present($restoredFavicon, 'favicon'));
        $this->get($faviconUrl)->assertNotFound();
        Cache::flush();
        $this->get('/')
            ->assertOk()
            ->assertSee('https://images.example.org/favicon-fallback.png', false)
            ->assertDontSee($faviconMedia->storage_key)
            ->assertDontSee('storage/app/private');
    }

    public function test_restore_refuses_inexact_destructive_confirmation(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Keep This Group']);
        $backup = app(CreateBackup::class)->handle('manual');

        try {
            app(RestoreBackup::class)->assertRestorableRun($backup, 'restore waymark');
            $this->fail('The restore unexpectedly accepted an inexact confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirmation', $exception->getMessage());
        }

        $this->assertSame('Keep This Group', $profile->fresh()->group_name);
    }

    #[DataProvider('incompatibleBackupVersions')]
    public function test_different_release_backup_is_rejected_before_safety_backup_or_maintenance(string $backupVersion): void
    {
        config()->set('waymark.version', '1.0.0');
        $profile = SiteProfile::query()->create(['group_name' => 'Current Group']);
        $backup = app(CreateBackup::class)->handle('manual');
        $path = Storage::disk('backups')->path($backup->storage_path);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        $manifest = json_decode($archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['waymark_version'] = $backupVersion;
        $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $archive->close();

        try {
            app(RestoreBackup::class)->restoreFile($path, 'RESTORE WAYMARK');
            $this->fail('The different-release backup unexpectedly restored.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('matching Waymark release', $exception->getMessage());
        }

        $this->assertSame('Current Group', $profile->fresh()->group_name);
        $this->assertFalse(app(MaintenanceManager::class)->active());
        $this->assertDatabaseMissing('backup_runs', ['trigger' => 'pre-restore']);
    }

    public static function incompatibleBackupVersions(): array
    {
        return [
            'older release' => ['0.9.0'],
            'newer release' => ['99.0.0'],
        ];
    }

    public function test_interrupted_restore_uses_safety_backup_and_keeps_maintenance_active(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Target Backup State']);
        $target = app(CreateBackup::class)->handle('manual');
        $profile->update(['group_name' => 'Current Safe State']);
        Storage::disk('local')->put('documents/current.pdf', 'current-document');
        $safety = app(CreateBackup::class)->handle('pre-restore');
        $this->app->instance(RestoreHealthProbe::class, new class implements RestoreHealthProbe
        {
            private int $attempts = 0;

            public function assertHealthy(): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new RuntimeException('controlled restore interruption');
                }
            }
        });

        try {
            app(RestoreBackup::class)->fromRunWithSafetyBackup($target, $safety, 'RESTORE WAYMARK');
            $this->fail('The interrupted restore unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
        }

        $this->assertSame('Current Safe State', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('documents/current.pdf');
        $this->assertTrue(app(MaintenanceManager::class)->active());
    }

    /** @return array{SiteMedia, array<string, string>} */
    private function managedMedia(User $creator, SiteMediaPurpose $purpose, string $mimeType, ?string $alt = null): array
    {
        $storageKey = (string) Str::uuid();
        $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
        $variantNames = $purpose === SiteMediaPurpose::SiteFavicon
            ? ['favicon']
            : ['master', 'large', 'medium', 'thumbnail'];
        $files = [];
        foreach ($variantNames as $variant) {
            $path = "site-media/{$storageKey}/{$variant}.{$extension}";
            $files[$path] = $purpose->value.'-'.$variant.'-bytes';
            Storage::disk('local')->put($path, $files[$path]);
        }

        $media = SiteMedia::query()->create([
            'created_by_user_id' => $creator->id,
            'storage_key' => $storageKey,
            'storage_disk' => 'local',
            'processed_variants' => array_combine($variantNames, array_keys($files)),
            'mime_type' => $mimeType,
            'width' => 512,
            'height' => $purpose === SiteMediaPurpose::SiteFavicon ? 512 : 320,
            'file_size_bytes' => array_sum(array_map('strlen', $files)),
            'alt_text' => $alt,
            'is_decorative' => $purpose !== SiteMediaPurpose::WalkFeaturedImage,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
            'purpose' => $purpose,
            'orphaned_at' => null,
        ]);

        return [$media, $files];
    }
}
