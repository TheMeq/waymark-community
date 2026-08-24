<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Updates\UpdateStateStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UpdateInstallationPageTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('framework/testing/update-page-'.bin2hex(random_bytes(8)).'.json');
        config()->set('waymark.updates.state_path', $this->statePath);
        app(UpdateStateStore::class)->write([
            'status' => 'checked', 'checked_at' => now()->toIso8601String(), 'current_version' => '1.0.0', 'update_available' => true,
            'metadata' => ['version' => '1.2.0', 'security_release' => true, 'summary' => 'Security release.', 'release_notes' => ['Important hardening.']],
            'compatibility' => ['compatible' => true, 'checks' => []],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        parent::tearDown();
    }

    public function test_verified_compatible_release_shows_guarded_install_confirmation(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)->get('/admin/update-centre')
            ->assertSuccessful()
            ->assertSee('Type UPDATE WAYMARK')
            ->assertSee('Install verified update');
    }

    public function test_install_submission_requires_sensitive_assurance_and_exact_confirmation(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($administrator)
            ->post('/admin/update-centre/install', ['confirmation' => 'UPDATE WAYMARK'])
            ->assertRedirect(route('password.confirm'));

        $this->withSession([
            'auth.password_confirmed_at' => now()->unix(),
            SensitiveActionAssurance::PASSWORD_CONFIRMED_USER_ID => $administrator->id,
        ])->post('/admin/update-centre/install', ['confirmation' => 'update waymark'])
            ->assertRedirect('/admin/update-centre')
            ->assertSessionHasErrors('confirmation');
    }
}
