<?php

namespace Tests\Feature\Auth;

use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_ordinary_authenticated_account_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_authorised_active_admin_can_access_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_suspended_admin_cannot_access_admin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertForbidden();
    }
}
