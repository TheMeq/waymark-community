<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MaintenancePageTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('framework/testing/maintenance-page-'.bin2hex(random_bytes(8)).'.json');
        config()->set('waymark.maintenance.state_path', $this->statePath);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        parent::tearDown();
    }

    public function test_maintenance_configuration_requires_administrator_and_sensitive_assurance(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)->get('/admin/maintenance-mode')->assertRedirect(route('password.confirm'));
        $this->withSession($this->assuredSession($administrator))->get('/admin/maintenance-mode')
            ->assertSuccessful()->assertSee('Maintenance mode');
    }

    public function test_administrator_can_enable_and_disable_maintenance_with_a_private_bypass(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        $response = $this->post(route('admin.maintenance.enable'), [
            'message' => 'A short planned maintenance window.',
            'expected_return_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'contact_url' => 'https://status.example.test/waymark',
        ])->assertRedirect('/admin/maintenance-mode')->assertPlainCookie(MaintenanceManager::BYPASS_COOKIE);

        $this->assertTrue(app(MaintenanceManager::class)->active());

        $bypass = $response->getCookie(MaintenanceManager::BYPASS_COOKIE, false)?->getValue();
        $this->withUnencryptedCookie(MaintenanceManager::BYPASS_COOKIE, (string) $bypass)
            ->post(route('admin.maintenance.disable'))
            ->assertRedirect('/admin/maintenance-mode')
            ->assertCookieExpired(MaintenanceManager::BYPASS_COOKIE);
        $this->assertFalse(app(MaintenanceManager::class)->active());
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
