<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Queries\PublicNews;
use App\Domain\Content\Queries\VisibleTestimonials;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class NewsTestimonialTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_publication_schedule_and_expiry_control_active_listing_but_preserve_archive(): void
    {
        $author = User::factory()->create();
        NewsArticle::query()->create($this->article($author, ['slug' => 'draft', 'publication_state' => 'draft']));
        NewsArticle::query()->create($this->article($author, ['slug' => 'future', 'publish_at' => now()->addHour()]));
        $expired = NewsArticle::query()->create($this->article($author, ['slug' => 'expired', 'expires_at' => now()->subMinute()]));
        $current = NewsArticle::query()->create($this->article($author, ['slug' => 'current']));

        $this->assertSame([$current->id], app(PublicNews::class)->active()->pluck('id')->all());
        $this->assertSame([$current->id, $expired->id], app(PublicNews::class)->archive()->pluck('id')->all());
        $this->get('/news/current')->assertOk()->assertSeeText('Trail update');
        $this->get('/news/expired')->assertOk();
        $this->get('/news/draft')->assertNotFound();
    }

    public function test_news_featured_image_uses_site_media_and_invalid_or_missing_media_omits_cleanly(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $path = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';
        Storage::disk('local')->put($path, 'image');
        $media = SiteMedia::query()->create(['created_by_user_id' => $author->id, 'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300', 'storage_disk' => 'local', 'processed_variants' => ['master' => $path], 'mime_type' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'file_size_bytes' => 5, 'alt_text' => 'Walkers at the summer gathering', 'is_decorative' => false, 'focal_point_x' => .5, 'focal_point_y' => .5, 'processing_status' => 'complete', 'health_status' => 'healthy']);
        $withImage = NewsArticle::query()->create($this->article($author, ['slug' => 'with-image', 'featured_media_id' => $media->id]));
        NewsArticle::query()->create($this->article($author, ['slug' => 'without-image', 'featured_media_id' => null]));

        $this->get('/news/'.$withImage->slug)->assertOk()->assertSee('alt="Walkers at the summer gathering"', false)->assertSee(route('site-media.stream', [$media, 'master']), false);
        $this->get('/news/without-image')->assertOk()->assertDontSee('<img', false);
    }

    public function test_testimonials_are_curated_visible_and_ordered(): void
    {
        Testimonial::query()->create(['quote' => 'Second quote', 'display_name' => 'Sam P.', 'active' => true, 'sort_order' => 20]);
        Testimonial::query()->create(['quote' => 'First quote', 'display_name' => 'Jo R.', 'active' => true, 'featured' => true, 'sort_order' => 10]);
        Testimonial::query()->create(['quote' => 'Hidden quote', 'display_name' => 'Private', 'active' => false, 'sort_order' => 1]);

        $this->assertSame(['First quote', 'Second quote'], app(VisibleTestimonials::class)->get()->pluck('quote')->all());
        $this->get('/')->assertOk()->assertSeeText('First quote')->assertDontSeeText('Hidden quote');
    }

    public function test_news_and_testimonial_admin_are_content_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        foreach (['/admin/news-articles', '/admin/testimonials'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function article(User $author, array $overrides = []): array
    {
        return array_merge(['title' => 'Trail update', 'slug' => 'trail-update', 'summary' => 'A concise group update.', 'blocks' => [['type' => 'rich_text', 'content' => '<p>News from the trail.</p>']], 'author_id' => $author->id, 'primary_category' => 'Group news', 'tags' => ['community'], 'publication_state' => 'published', 'publish_at' => now()->subHour(), 'expires_at' => null, 'featured_on_homepage' => false, 'share_title' => 'Trail update'], $overrides);
    }
}
