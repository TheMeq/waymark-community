<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Imports\GuidedCsvImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CsvProgrammeReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_undated_rows_are_reported_and_skipped_without_blocking_valid_walks(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $path = tempnam(sys_get_temp_dir(), 'waymark-csv-');
        file_put_contents($path, "title,starts_at,leader_email,import_enabled\nIdea,,,false\nKnown walk,2023-05-07 10:00,{$leader->email},true\n");
        try {
            $mapping = array_combine(['title', 'starts_at', 'leader_email', 'import_enabled'], ['title', 'starts_at', 'leader_email', 'import_enabled']);
            $service = app(GuidedCsvImport::class);
            $preview = $service->preview('walks', $path, $mapping);
            $this->assertSame(['skipped', 'valid'], array_column($preview->rows, 'status'));
            $result = $service->execute('walks', $path, $mapping);
            $this->assertTrue($result->committed);
            $this->assertSame(1, $result->imported);
            $this->assertSame('Known walk', Event::query()->sole()->title);
        } finally {
            unlink($path);
        }
    }

    public function test_removed_xlsx_import_screen_is_no_longer_routed(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]));
        $this->get('/admin/walk-programme-import')->assertNotFound();
    }
}
