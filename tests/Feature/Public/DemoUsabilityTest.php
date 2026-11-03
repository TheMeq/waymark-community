<?php

namespace Tests\Feature\Public;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoUsabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_navigation_is_available_on_mobile_and_desktop(): void
    {
        NavigationItem::query()->create(['label' => 'Our story', 'url' => '/our-story', 'enabled' => true, 'sort_order' => 1]);
        $response = $this->get('/login')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['Mobile navigation', 'Primary navigation'] as $label) {
            $this->assertSame(1, $xpath->query('//nav[@aria-label="'.$label.'"]//a[@href="/our-story"]')->length);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="'.$label.'"]//a[normalize-space(.)="Account"]')->length);
        }
    }

    public function test_dashboard_review_counts_link_to_the_relevant_admin_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => AccountRole::Administrator]))
            ->get('/admin')->assertOk()
            ->assertSee('href="'.url('/admin/photo-moderation').'"', false)
            ->assertSee('href="'.url('/admin/documents').'"', false)
            ->assertSee('href="'.url('/admin/membership-verification').'"', false)
            ->assertSee('Public calendar');
    }

    public function test_joining_page_explains_attending_and_separates_online_accounts(): void
    {
        $this->get('/new-here')->assertOk()
            ->assertSee('Before you set off')
            ->assertSee('Online account')
            ->assertDontSee('identity-checked features');
    }

    public function test_upcoming_walk_links_to_editing_for_its_leader(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $event = Event::factory()->create([
            'organiser_id' => $leader->id,
            'type' => EventType::Walk,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);
        $walk = Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $leader->id]);
        $this->actingAs($leader)->get('/admin')->assertOk()
            ->assertSee('href="'.WalkResource::getUrl('edit', ['record' => $walk]).'"', false);
    }
}
