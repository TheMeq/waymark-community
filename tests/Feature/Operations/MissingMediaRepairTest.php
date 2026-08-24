<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Health\Actions\RecheckMissingMedia;
use App\Domain\Operations\Health\Actions\ScanMissingMedia;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MissingMediaRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_site_media_is_queued_without_deleting_its_database_record(): void
    {
        Storage::fake('local');
        $media = $this->siteMedia('missing.jpg');

        $result = app(ScanMissingMedia::class)->handle();

        $this->assertSame(1, $result->missing);
        $this->assertDatabaseHas('missing_media_repairs', [
            'media_type' => SiteMedia::class,
            'record_id' => $media->id,
            'path' => $media->processed_variants['large'],
            'status' => 'queued',
        ]);
        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertDatabaseHas('site_media', ['id' => $media->id]);
    }

    public function test_recheck_resolves_queue_only_after_the_expected_file_exists(): void
    {
        Storage::fake('local');
        $media = $this->siteMedia('restored.jpg');
        app(ScanMissingMedia::class)->handle();
        $repair = MissingMediaRepair::query()->sole();

        $this->assertFalse(app(RecheckMissingMedia::class)->handle($repair));
        Storage::disk('local')->put($repair->path, 'restored-image');
        $this->assertTrue(app(RecheckMissingMedia::class)->handle($repair->fresh()));

        $this->assertSame('resolved', $repair->fresh()->status);
        $this->assertSame('healthy', $media->fresh()->health_status);
    }

    private function siteMedia(string $filename): SiteMedia
    {
        $key = (string) Str::uuid();

        return SiteMedia::query()->create([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_key' => $key,
            'storage_disk' => 'local',
            'processed_variants' => ['large' => "site-media/{$key}/{$filename}"],
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
            'file_size_bytes' => 1024,
            'alt_text' => 'A walking group on a hill',
            'is_decorative' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
        ]);
    }
}
