<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Events\Models\Event;
use App\Filament\Pages\ImportsExports;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

final class ImportsExportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_page_requires_an_administrator_and_recent_sensitive_assurance(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)->get('/admin/imports-exports')->assertRedirect(route('password.confirm'));

        $this->withSession($this->assuredSession($administrator))
            ->get('/admin/imports-exports')
            ->assertSuccessful()
            ->assertSee('Imports and exports')
            ->assertSee('Guided CSV import')
            ->assertSee('Walks')
            ->assertSee('Preview and dry run');

        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $this->actingAs($member)->withSession($this->assuredSession($member))
            ->get('/admin/imports-exports')->assertForbidden();
    }

    public function test_administrator_maps_previews_and_commits_a_valid_walk_csv(): void
    {
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'email' => 'administrator@example.test',
        ]);
        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        $component = Livewire::test(ImportsExports::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('walks.csv', implode("\n", [
                'Walk title,Start,Leader',
                'Release readiness walk,2026-09-10 10:00,administrator@example.test',
            ])))
            ->call('inspectCsv')
            ->assertSet('headers', ['Walk title', 'Start', 'Leader'])
            ->set('mapping.title', 'Walk title')
            ->set('mapping.starts_at', 'Start')
            ->set('mapping.leader_email', 'Leader')
            ->call('previewImport')
            ->assertSet('previewSummary.valid', 1)
            ->assertSet('previewSummary.invalid', 0)
            ->assertSee('Release readiness walk')
            ->call('runImport')
            ->assertSet('resultSummary.imported', 1)
            ->assertSee('Imported 1 row');

        $this->assertSame('Release readiness walk', Event::query()->sole()->title);
    }

    public function test_invalid_preview_exposes_a_downloadable_row_report_and_cannot_commit(): void
    {
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'email' => 'administrator@example.test',
        ]);
        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        Livewire::test(ImportsExports::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('walks.csv', implode("\n", [
                'title,starts_at,leader_email',
                'Broken walk,not-a-date,administrator@example.test',
            ])))
            ->call('inspectCsv')
            ->set('mapping.title', 'title')
            ->set('mapping.starts_at', 'starts_at')
            ->set('mapping.leader_email', 'leader_email')
            ->call('previewImport')
            ->assertSet('previewSummary.invalid', 1)
            ->call('runImport')
            ->assertSet('resultSummary.committed', false)
            ->call('downloadImportReport')
            ->assertFileDownloaded('waymark-walks-import-report.csv');

        $this->assertDatabaseCount('events', 0);
    }

    /** @return array<string, int> */
    private function assuredSession(User $user): array
    {
        return [
            'auth.password_confirmed_at' => now()->unix(),
            SensitiveActionAssurance::PASSWORD_CONFIRMED_USER_ID => $user->id,
        ];
    }
}
