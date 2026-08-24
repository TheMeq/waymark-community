<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\PruneBackups;
use App\Domain\Operations\Backups\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_keeps_the_newest_completed_sets_and_deletes_only_their_known_files(): void
    {
        Storage::fake('backups');
        foreach (range(1, 4) as $number) {
            $path = "waymark-backup-{$number}.zip";
            Storage::disk('backups')->put($path, "backup-{$number}");
            BackupRun::query()->create([
                'status' => 'completed', 'trigger' => 'scheduled', 'storage_disk' => 'backups', 'storage_path' => $path,
                'sha256' => hash('sha256', "backup-{$number}"), 'size_bytes' => 8, 'completed_at' => now()->addMinutes($number),
            ]);
        }
        Storage::disk('backups')->put('unrelated-file.txt', 'keep');

        $removed = app(PruneBackups::class)->handle(2);

        $this->assertSame(2, $removed);
        $this->assertSame(2, BackupRun::query()->where('status', 'completed')->count());
        Storage::disk('backups')->assertExists('waymark-backup-4.zip');
        Storage::disk('backups')->assertExists('waymark-backup-3.zip');
        Storage::disk('backups')->assertExists('unrelated-file.txt');
    }
}
