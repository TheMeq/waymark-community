<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Filament\Pages\ImportsExports;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class CsvProgrammePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($admin);
        session()->put(['auth.password_confirmed_at' => now()->unix(), SensitiveActionAssurance::PASSWORD_CONFIRMED_USER_ID => $admin->id]);
    }

    public function test_holiday_csv_auto_maps_headers_and_creates_a_private_date_only_draft(): void
    {
        Livewire::test(ImportsExports::class)->set('definition', 'holidays')
            ->set('csvFile', UploadedFile::fake()->createWithContent('holidays.csv', "title,starts_at,start_time_unknown,destination\nHighland weekend,2023-04-06,true,Highlands\n"))
            ->call('inspectCsv')->call('previewImport')->assertHasNoErrors()
            ->assertSet('previewSummary.valid', 1)
            ->call('runImport')->assertHasNoErrors()->assertSet('resultSummary.imported', 1);
        $event = Event::query()->sole();
        $this->assertSame(EventType::Holiday, $event->type);
        $this->assertTrue($event->start_time_unknown);
        $this->assertFalse($event->is_public);
        $this->assertSame('Highlands', $event->holiday->destination);
    }

    public function test_event_csv_creates_a_social_and_keeps_venue_details(): void
    {
        Livewire::test(ImportsExports::class)->set('definition', 'events')
            ->set('csvFile', UploadedFile::fake()->createWithContent('events.csv', "title,starts_at,venue_name\nSummer social,2023-06-01 19:30,Village hall\n"))
            ->call('inspectCsv')->call('previewImport')->assertHasNoErrors()
            ->call('runImport')->assertHasNoErrors()->assertSet('resultSummary.imported', 1);
        $this->assertSame('Village hall', Event::query()->sole()->social->venue_name);
    }

    public function test_walk_csv_skips_undated_ideas_and_admin_can_review_source_data(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ['title', 'starts_at', 'leader_name', 'import_enabled', 'start_time_unknown', 'source_payload', 'review_notes'], ',', '"', '');
        fputcsv($stream, ['Archive walk', '2023-05-07', 'Visiting Leader', 'true', 'true', json_encode([['sheet' => 'Historic', 'row' => 9, 'cells' => ['D9' => ['header' => 'Title', 'value' => 'Original archive walk']]]]), 'Confirm the route'], ',', '"', '');
        fputcsv($stream, ['Unscheduled idea', '', '', 'false', '', '', 'Date unconfirmed'], ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        Livewire::test(ImportsExports::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('walks.csv', $csv))
            ->call('inspectCsv')->call('previewImport')->assertHasNoErrors()
            ->assertSet('previewSummary.skipped', 1)->assertSet('previewSummary.valid', 1)
            ->call('runImport')->assertHasNoErrors()->assertSet('resultSummary.imported', 1);
        $event = Event::query()->sole();
        $this->assertSame('Visiting Leader', $event->walk->primary_leader_name);
        $this->assertSame('Confirm the route', $event->import_source['review_notes']);
        Livewire::test(EditWalk::class, ['record' => $event->walk->getRouteKey()])
            ->assertSee('Imported source record')
            ->assertFormSet(['start_time_unknown' => true, 'import_source_record' => "Review notes: Confirm the route\n\nHistoric, row 9\nD9 · Title: Original archive walk"]);
    }

    public function test_changed_mapping_cannot_use_an_older_preview(): void
    {
        Livewire::test(ImportsExports::class)->set('definition', 'events')
            ->set('csvFile', UploadedFile::fake()->createWithContent('events.csv', "title,starts_at,summary\nSummer social,2023-06-01 19:30,Old summary\n"))
            ->call('inspectCsv')->call('previewImport')
            ->set('mapping.summary', '')
            ->call('runImport')->assertHasErrors('mapping');
        $this->assertDatabaseCount('events', 0);
    }
}
