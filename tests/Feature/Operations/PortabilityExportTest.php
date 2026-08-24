<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Portability\Actions\AdvancePendingPortabilityExports;
use App\Domain\Operations\Portability\Actions\CreatePortabilityExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class PortabilityExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_export_contains_structured_records_media_documents_and_a_verified_machine_manifest(): void
    {
        Storage::disk('local')->put('documents/policy/v1.pdf', 'policy document');
        Storage::disk('local')->put('community-photos/one/master.jpg', 'community photo');
        Storage::disk('public')->put('site/brand-mark.png', 'brand mark');
        Storage::disk('local')->put('account-exports/1/private.json', 'derived personal export');

        $run = app(CreatePortabilityExport::class)->start();
        $this->assertSame('queued', $run->status);

        do {
            $run = app(CreatePortabilityExport::class)->advance($run, 1);
        } while (in_array($run->status, ['queued', 'running'], true));

        $this->assertSame('completed', $run->status);
        $this->assertSame('local', $run->storage_disk);
        $this->assertStringStartsWith('portability-exports/', $run->storage_path);
        Storage::disk('local')->assertExists($run->storage_path);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($run->storage_path)), $run->sha256);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('local')->path($run->storage_path)));
        $manifest = json_decode((string) $archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('waymark-portability', $manifest['format']);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame(config('waymark.version'), $manifest['waymark_version']);
        $paths = array_column($manifest['components'], 'path');
        $this->assertContains('records/database.jsonl', $paths);
        $this->assertContains('files/local/documents/policy/v1.pdf', $paths);
        $this->assertContains('files/local/community-photos/one/master.jpg', $paths);
        $this->assertContains('files/public/site/brand-mark.png', $paths);
        $this->assertNotContains('files/local/account-exports/1/private.json', $paths);
        $this->assertFalse($archive->locateName('configuration/restoration.env'));

        foreach ($manifest['components'] as $component) {
            $contents = $archive->getFromName($component['path']);
            $this->assertIsString($contents);
            $this->assertSame(hash('sha256', $contents), $component['sha256']);
            $this->assertSame(strlen($contents), $component['size_bytes']);
        }
        $archive->close();
    }

    public function test_pending_export_advancer_performs_bounded_resumable_work(): void
    {
        Storage::disk('local')->put('documents/one.pdf', 'one');
        Storage::disk('local')->put('documents/two.pdf', 'two');
        $run = app(CreatePortabilityExport::class)->start();

        $this->assertSame(1, app(AdvancePendingPortabilityExports::class)->handle(1, 1));
        $this->assertSame('copy_files', $run->fresh()->stage);
        $this->assertSame(1, app(AdvancePendingPortabilityExports::class)->handle(1, 1));
        $this->assertSame(1, $run->fresh()->work_state['file_index']);
        $this->assertSame(1, app(AdvancePendingPortabilityExports::class)->handle(1, 1));
        $this->assertSame(2, $run->fresh()->work_state['file_index']);
    }

    public function test_export_fails_safely_when_a_source_file_changes_during_bounded_generation(): void
    {
        Storage::disk('local')->put('documents/policy.pdf', 'original');
        $run = app(CreatePortabilityExport::class)->start();
        $run = app(CreatePortabilityExport::class)->advance($run);
        Storage::disk('local')->put('documents/policy.pdf', 'changed');

        $this->expectException(RuntimeException::class);
        try {
            app(CreatePortabilityExport::class)->advance($run);
        } finally {
            $this->assertSame('failed', $run->fresh()->status);
            $this->assertTrue($run->fresh()->retryable);
            Storage::disk('local')->assertMissing('portability-exports/'.$run->id.'.zip');
        }
    }

    public function test_portability_archive_is_not_accepted_as_a_disaster_recovery_backup(): void
    {
        $run = app(CreatePortabilityExport::class)->handle();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity');
        app(BackupVerifier::class)->open(Storage::disk('local')->path($run->storage_path));
    }
}
