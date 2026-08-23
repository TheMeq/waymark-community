<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Actions\CreateCmsReviewLink;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Queries\PublicCmsPages;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_and_due_pages_are_public(): void
    {
        CmsPage::query()->create($this->page(['slug' => 'draft', 'publication_state' => 'draft']));
        CmsPage::query()->create($this->page(['slug' => 'future', 'publish_at' => now()->addHour()]));
        $published = CmsPage::query()->create($this->page(['slug' => 'walking-with-us', 'publish_at' => now()->subMinute()]));

        $this->assertSame([$published->id], app(PublicCmsPages::class)->query()->pluck('id')->all());
        $this->get('/pages/draft')->assertNotFound();
        $this->get('/pages/future')->assertNotFound();
        $this->get('/pages/walking-with-us')->assertOk()->assertSeeText('Walking with us');
    }

    public function test_public_page_uses_the_approved_public_site_chrome(): void
    {
        CmsPage::query()->create($this->page());

        $this->get('/pages/walking-with-us')
            ->assertOk()
            ->assertSeeText('Upcoming walks')
            ->assertSeeText('Privacy')
            ->assertSeeText('Accessibility');
    }

    public function test_blocks_are_constrained_and_rendered_without_arbitrary_markup(): void
    {
        $page = CmsPage::query()->create($this->page([
            'blocks' => [
                ['type' => 'rich_text', 'content' => '<p>Friendly <strong>walking</strong>.</p><script>alert(1)</script>'],
                ['type' => 'callout', 'heading' => 'Bring layers', 'body' => 'Hill weather changes quickly.'],
                ['type' => 'faq', 'items' => [['question' => 'Do I need boots?', 'answer' => 'Usually, yes.']]],
            ],
        ]));

        $response = $this->get('/pages/'.$page->slug)->assertOk();

        $response->assertSee('<strong>walking</strong>', false)
            ->assertDontSee('<script>', false)
            ->assertSeeText('Bring layers')
            ->assertSeeText('Do I need boots?');

        $this->expectException(ValidationException::class);
        CmsPage::query()->create($this->page(['slug' => 'unsafe', 'blocks' => [['type' => 'custom_html', 'content' => '<iframe>']]]));
    }

    public function test_every_approved_block_has_a_bounded_public_rendering_contract(): void
    {
        Storage::fake('local');
        $path = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';
        Storage::disk('local')->put($path, 'image');
        $media = SiteMedia::query()->create([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_disk' => 'local',
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
            'processed_variants' => ['master' => $path],
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
            'file_size_bytes' => 100,
            'alt_text' => 'Leaders on a ridge',
            'is_decorative' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
        ]);
        $page = CmsPage::query()->create($this->page([
            'blocks' => [
                ['type' => 'image_text', 'media_id' => $media->id, 'heading' => 'Image story', 'body' => 'A safe media reference.'],
                ['type' => 'quote', 'quote' => 'The best Saturdays.', 'attribution' => 'A walker'],
                ['type' => 'cta', 'heading' => 'Come along', 'body' => 'Choose a walk.', 'label' => 'See walks', 'url' => '/walks'],
                ['type' => 'button_group', 'heading' => 'Useful links', 'items' => [['label' => 'Contact', 'url' => '/contact']]],
                ['type' => 'document_list', 'heading' => 'Downloads', 'items' => [['label' => 'Walking guide', 'url' => '/documents/walking-guide']]],
                ['type' => 'gallery', 'heading' => 'From the hills', 'media_ids' => [$media->id]],
                ['type' => 'statistics', 'heading' => 'At a glance', 'items' => [['value' => '42', 'label' => 'walks']]],
                ['type' => 'timeline', 'heading' => 'Our story', 'items' => [['label' => '2026', 'body' => 'Waymark launched.']]],
                ['type' => 'columns', 'heading' => 'Plan your day', 'items' => [['label' => 'Before', 'body' => 'Check the route.'], ['label' => 'After', 'body' => 'Share photos.']]],
            ],
        ]));

        $response = $this->get('/pages/'.$page->slug)->assertOk();
        foreach (['Image story', 'The best Saturdays.', 'See walks', 'Contact', 'Walking guide', '42', 'Waymark launched.', 'Share photos.'] as $copy) {
            $response->assertSeeText($copy);
        }
        $response->assertSee('alt="Leaders on a ridge"', false)->assertSee(route('site-media.stream', [$media, 'master']), false);
    }

    public function test_block_links_and_limited_columns_reject_unsafe_or_unbounded_structures(): void
    {
        foreach ([
            [['type' => 'cta', 'heading' => 'Unsafe', 'label' => 'Open', 'url' => 'javascript:alert(1)']],
            [['type' => 'columns', 'items' => [['body' => '1'], ['body' => '2'], ['body' => '3'], ['body' => '4']]]],
        ] as $blocks) {
            try {
                CmsPage::query()->create($this->page(['slug' => 'unsafe-'.md5(serialize($blocks)), 'blocks' => $blocks]));
                $this->fail('An unsafe or unbounded block structure was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_authenticated_preview_and_expiring_revocable_review_links_are_secure(): void
    {
        $page = CmsPage::query()->create($this->page(['publication_state' => 'draft']));
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        $this->get(route('cms.preview', $page))->assertRedirect('/login');
        $this->actingAs($member)->get(route('cms.preview', $page))->assertForbidden();
        $this->actingAs($administrator)->get(route('cms.preview', $page))->assertOk()->assertSeeText('Draft preview');

        $review = app(CreateCmsReviewLink::class)->handle($administrator, $page, now()->addHour());
        $this->assertTrue(Hash::check($review->plainToken, $review->link->token_hash));
        $this->get(route('cms.review', $review->plainToken))->assertOk()->assertSeeText('Review copy');

        $review->link->update(['revoked_at' => now()]);
        $this->get(route('cms.review', $review->plainToken))->assertNotFound();

        $expired = app(CreateCmsReviewLink::class)->handle($administrator, $page, now()->subMinute());
        $this->get(route('cms.review', $expired->plainToken))->assertNotFound();
    }

    public function test_cms_administration_is_restricted_to_content_managers(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        $this->actingAs($administrator)->get('/admin/cms-pages')->assertOk()->assertSeeText('Pages');
        $this->actingAs($member)->get('/admin/cms-pages')->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function page(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Walking with us',
            'slug' => 'walking-with-us',
            'publication_state' => 'published',
            'publish_at' => now()->subMinute(),
            'blocks' => [['type' => 'rich_text', 'content' => '<p>Review copy</p>']],
        ], $overrides);
    }
}
