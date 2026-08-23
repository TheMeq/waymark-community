<?php

namespace Tests\Feature\Discovery;

use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SeoFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_metadata_uses_editable_values_and_a_query_free_canonical_url(): void
    {
        $page = CmsPage::query()->create([
            'title' => 'Walking with us',
            'slug' => 'walking-with-us',
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Join a friendly walk.</p>']],
            'publication_state' => 'published',
            'publish_at' => now()->subMinute(),
            'seo_title' => 'Walking with our community',
            'seo_description' => 'Practical guidance for joining a community walk.',
        ]);

        $this->get(route('cms.show', $page->slug).'?utm_source=review')
            ->assertOk()
            ->assertSee('<title>Walking with our community</title>', false)
            ->assertSee('<meta name="description" content="Practical guidance for joining a community walk.">', false)
            ->assertSee('<link rel="canonical" href="'.route('cms.show', $page->slug).'">', false)
            ->assertSee('<meta property="og:title" content="Walking with our community">', false);
    }

    public function test_news_and_events_emit_truthful_open_graph_and_structured_data(): void
    {
        SiteProfile::query()->create(['group_name' => 'Peak Friends']);
        $author = User::factory()->create();
        $article = NewsArticle::query()->create([
            'title' => 'Path repair update', 'slug' => 'path-repair-update', 'summary' => 'Work has finished.',
            'blocks' => [['type' => 'rich_text', 'content' => '<p>The path is open.</p>']], 'author_id' => $author->id,
            'primary_category' => 'News', 'publication_state' => 'published', 'publish_at' => now()->subHour(),
            'share_title' => 'The path is open', 'share_description' => 'Read the latest path update.',
        ]);
        $event = $this->publishedWalk();

        $news = $this->get(route('news.show', $article->slug))->assertOk();
        $news->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('"headline":"The path is open"', false);

        $walk = $this->get(route('walks.show', $event->slug))->assertOk();
        $walk->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('"@type":"Event"', false)
            ->assertSee('"name":"Sunrise ridge walk"', false)
            ->assertSee('"organizer":{"@type":"Organization","name":"Peak Friends"', false);
    }

    public function test_expired_news_remains_available_but_is_noindex(): void
    {
        $article = NewsArticle::query()->create([
            'title' => 'Old notice', 'slug' => 'old-notice', 'summary' => null,
            'blocks' => [], 'author_id' => User::factory()->create()->id, 'primary_category' => 'Notice',
            'publication_state' => 'published', 'publish_at' => now()->subMonth(), 'expires_at' => now()->subDay(),
        ]);

        $this->get(route('news.show', $article->slug))->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_sitemap_contains_only_eligible_public_records_and_robots_points_to_it(): void
    {
        $visible = CmsPage::query()->create(['title' => 'Visible page', 'slug' => 'visible-page', 'blocks' => [], 'publication_state' => 'published', 'publish_at' => now()->subMinute()]);
        $draft = CmsPage::query()->create(['title' => 'Draft page', 'slug' => 'draft-page', 'blocks' => [], 'publication_state' => 'draft']);
        $walk = $this->publishedWalk();

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $sitemap->assertSee(route('cms.show', $visible->slug), false)
            ->assertSee(route('walks.show', $walk->slug), false)
            ->assertDontSee(route('cms.show', $draft->slug), false);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    private function publishedWalk(): Event
    {
        $leader = User::factory()->create();
        $event = Event::factory()->for($leader, 'organiser')->create([
            'type' => EventType::Walk, 'title' => 'Sunrise ridge walk', 'slug' => 'sunrise-ridge-walk',
            'summary' => 'A steady climb to the ridge.', 'status' => EventStatus::Published, 'is_public' => true,
            'published_at' => now()->subDay(), 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(4),
        ]);
        Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $leader->id, 'meeting_location_name' => 'Ridge car park']);

        return $event;
    }
}
