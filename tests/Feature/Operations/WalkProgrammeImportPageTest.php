<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Filament\Pages\WalkProgrammeImport as ImportPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\ProgrammeWorkbook;
use Tests\TestCase;

final class WalkProgrammeImportPageTest extends TestCase
{
    use ProgrammeWorkbook;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        Grade::query()->create(['name' => 'Moderate', 'description' => 'Moderate', 'display_order' => 1]);
    }

    public function test_upload_map_preview_and_import_creates_private_draft_for_the_mapped_leader(): void
    {
        $admin = $this->administrator();
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $path = $this->workbook([$this->programmeRow('Ridge circuit')]);
        Livewire::test(ImportPage::class)
            ->set('workbook', UploadedFile::fake()->createWithContent('programme.xlsx', file_get_contents($path)))
            ->call('readWorkbook')->assertHasNoErrors()
            ->assertSet('selectedRows', ['9'])
            ->set('leaderMapping.'.hash('sha256', 'Visiting Leader'), (string) $leader->id)
            ->call('previewImport')->assertHasNoErrors()
            ->assertSet('previewSummary.valid', 1)
            ->assertSee('Ridge circuit')
            ->call('runImport')->assertHasNoErrors()
            ->assertSet('resultSummary.imported', 1)
            ->assertSet('resultSummary.committed', true);
        $event = Event::query()->sole();
        $this->assertSame($leader->id, $event->organiser_id);
        $this->assertFalse($event->is_public);
    }

    public function test_changing_mapping_or_selection_requires_a_new_preview(): void
    {
        $this->administrator();
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $path = $this->workbook([$this->programmeRow('Ridge circuit')]);
        Livewire::test(ImportPage::class)
            ->set('workbook', UploadedFile::fake()->createWithContent('programme.xlsx', file_get_contents($path)))
            ->call('readWorkbook')
            ->call('previewImport')
            ->set('leaderMapping.'.hash('sha256', 'Visiting Leader'), (string) $leader->id)
            ->call('runImport')->assertHasErrors('preview')
            ->call('previewImport')
            ->set('selectedRows', [])
            ->call('runImport')->assertHasErrors('preview');
        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_route_requires_recent_assurance_and_rejects_members(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($admin)->get('/admin/walk-programme-import')->assertRedirect(route('password.confirm'));
        $this->administrator($admin);
        $this->get('/admin/walk-programme-import')->assertOk()->assertSee('Import walk programme');
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $this->actingAs($member)->get('/admin/walk-programme-import')->assertForbidden();
    }

    public function test_execution_requires_a_preview_and_current_sensitive_assurance(): void
    {
        $this->administrator();
        $path = $this->workbook([$this->programmeRow('Ridge circuit')]);
        $component = Livewire::test(ImportPage::class)
            ->set('workbook', UploadedFile::fake()->createWithContent('programme.xlsx', file_get_contents($path)))
            ->call('readWorkbook')
            ->call('runImport')->assertHasErrors('preview')
            ->call('previewImport');
        session()->forget('auth.password_confirmed_at');
        $component->call('runImport')->assertForbidden();
        $this->assertDatabaseCount('events', 0);
    }

    public function test_new_matching_walk_after_preview_requires_review_instead_of_using_stale_counts(): void
    {
        $admin = $this->administrator();
        $path = $this->workbook([$this->programmeRow('Ridge circuit')]);
        $component = Livewire::test(ImportPage::class)
            ->set('workbook', UploadedFile::fake()->createWithContent('programme.xlsx', file_get_contents($path)))
            ->call('readWorkbook')->call('previewImport');
        $event = Event::factory()->create(['title' => 'Ridge circuit', 'starts_at' => '2026-11-07 10:00:00']);
        Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $admin->id]);
        $component->call('runImport')->assertHasErrors('preview')->assertSet('previewSummary.duplicates', 1);
        $this->assertDatabaseCount('events', 1);
    }

    private function administrator(?User $admin = null): User
    {
        $admin ??= User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($admin);
        session()->put([
            'auth.password_confirmed_at' => now()->unix(),
            SensitiveActionAssurance::PASSWORD_CONFIRMED_USER_ID => $admin->id,
        ]);

        return $admin;
    }
}
