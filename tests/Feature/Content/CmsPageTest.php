<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Actions\CreateCmsReviewLink;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Queries\PublicCmsPages;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
