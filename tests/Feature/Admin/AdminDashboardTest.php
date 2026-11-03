<?php

namespace Tests\Feature\Admin;

use App\Domain\Accounts\Enums\AccountRole;
use App\Filament\Widgets\GettingStartedWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dashboard_is_task_focused_and_has_no_framework_promotion(): void
    {
        config()->set('waymark.email.configured', false);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Add a walk')
            ->assertSee('Add a social')
            ->assertSee('Add a holiday')
            ->assertSee('Manage photos')
            ->assertSee('Write news')
            ->assertSee('Add a document')
            ->assertSee('Upcoming events')
            ->assertSee('Documents due for review')
            ->assertSee('Membership reviews due')
            ->assertSee('Getting started')
            ->assertSee('Email delivery is not configured. Password resets, verification messages and notifications that rely on email will remain unavailable until it is set up.')
            ->assertSee('Configure email')
            ->assertDontSee('filamentphp.com', false)
            ->assertDontSee('Filament documentation');
    }

    public function test_walk_leader_has_a_one_click_add_walk_action_without_privileged_actions(): void
    {
        $leader = User::factory()->walkLeader()->create();

        $this->actingAs($leader)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Add a walk')
            ->assertSee('/admin/walks/create', false)
            ->assertDontSee('Add a social')
            ->assertDontSee('Add a holiday')
            ->assertDontSee('Add a document');
    }

    public function test_getting_started_panel_dismissal_persists_for_the_current_administrator(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        Livewire::actingAs($administrator)
            ->test(GettingStartedWidget::class)
            ->assertSee('Getting started')
            ->call('dismiss')
            ->assertDontSee('Getting started');

        $this->assertNotNull($administrator->refresh()->admin_onboarding_dismissed_at);
        $this->actingAs($administrator)->get('/admin')->assertDontSee('Getting started');
    }
}
