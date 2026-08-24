<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class BackupCreationTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/backup-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:restore-key\nDB_DATABASE=waymark\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
    }

    protected function tearDown(): void
    {
        if (is_file($this->environmentPath)) {
            unlink($this->environmentPath);
        }
        parent::tearDown();
    }

    public function test_manual_backup_contains_database_private_media_configuration_and_manifest(): void
    {
        SiteProfile::query()->create(['group_name' => 'Peak Pathfinders']);
        Storage::disk('local')->put('community-photos/example/large.jpg', 'image-data');

        $backup = app(CreateBackup::class)->handle('manual');

        $this->assertSame('completed', $backup->status);
        $this->assertSame('backups', $backup->storage_disk);
        Storage::disk('backups')->assertExists($backup->storage_path);
        $this->assertNotNull($backup->sha256);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('backups')->path($backup->storage_path)));
        $this->assertNotFalse($archive->locateName('manifest.json'));
        $this->assertNotFalse($archive->locateName('database.jsonl'));
        $this->assertNotFalse($archive->locateName('configuration/.env'));
        $this->assertNotFalse($archive->locateName('private/community-photos/example/large.jpg'));
        $this->assertStringContainsString('site_profiles', (string) $archive->getFromName('database.jsonl'));
        $archive->close();
    }

    public function test_backup_can_stream_to_an_s3_compatible_disk_without_changing_the_local_default(): void
    {
        Storage::fake('s3-backups');
        config()->set('waymark.backups.destination_disk', 's3-backups');

        $backup = app(CreateBackup::class)->handle('manual');

        $this->assertSame('s3-backups', $backup->storage_disk);
        Storage::disk('s3-backups')->assertExists($backup->storage_path);
        $this->assertSame(1, BackupRun::query()->where('status', 'completed')->count());
    }
}
