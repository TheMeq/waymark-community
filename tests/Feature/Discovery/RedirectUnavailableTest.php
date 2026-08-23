<?php

namespace Tests\Feature\Discovery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Actions\SavePublicRedirect;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\PublicRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RedirectUnavailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_a_public_slug_creates_an_exact_permanent_redirect(): void
    {
        $page = CmsPage::query()->create($this->page());
        $page->update(['slug' => 'join-a-walk']);

        $this->assertDatabaseHas('public_redirects', [
            'source_path' => '/pages/walking-with-us',
            'target_url' => '/pages/join-a-walk',
            'status_code' => 301,
            'automatic' => true,
        ]);
        $this->get('/pages/walking-with-us')->assertRedirect('/pages/join-a-walk')->assertStatus(301);
    }

    public function test_redirects_reject_loops_wildcards_and_unsafe_external_targets(): void
    {
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        app(SavePublicRedirect::class)->handle($actor, null, '/old-one', '/old-two', 301, true);

        foreach ([
            ['/old-two', '/old-one'],
            ['/old-*', '/walks'],
            ['/'.str_repeat('a', 512), '/walks'],
            ['/unsafe', 'http://tracking.example.test'],
            ['/self', '/self'],
        ] as [$source, $target]) {
            try {
                app(SavePublicRedirect::class)->handle($actor, null, $source, $target, 301, true);
                $this->fail("Unsafe redirect {$source} was accepted.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_known_unpublished_content_gets_a_helpful_unavailable_page_without_leaking_body(): void
    {
        CmsPage::query()->create($this->page([
            'title' => 'Retired joining guide',
            'slug' => 'retired-guide',
            'archived_at' => now(),
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Private editorial notes.</p>']],
        ]));

        $this->get('/pages/retired-guide')->assertNotFound()
            ->assertSeeText('Retired joining guide is unavailable')
            ->assertDontSeeText('Private editorial notes.')
            ->assertSeeText('Search Waymark');
    }

    public function test_unknown_pages_use_the_branded_helpful_404(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()
            ->assertSeeText('We could not find that page')
            ->assertSeeText('Upcoming walks')
            ->assertSeeText('Calendar')
            ->assertSeeText('Gallery')
            ->assertSeeText('Contact')
            ->assertSee('action="'.route('search').'"', false);
    }

    public function test_redirect_administration_is_content_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        PublicRedirect::withoutEvents(fn () => PublicRedirect::query()->create(['source_path' => '/old', 'target_url' => '/walks', 'status_code' => 301, 'enabled' => true]));

        $this->actingAs($administrator)->get('/admin/public-redirects')->assertOk()->assertSeeText('Redirects');
        $this->actingAs($member)->get('/admin/public-redirects')->assertForbidden();
    }

    private function page(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Walking with us', 'slug' => 'walking-with-us',
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Public guide.</p>']],
            'publication_state' => 'published', 'publish_at' => now()->subMinute(),
        ], $overrides);
    }
}
