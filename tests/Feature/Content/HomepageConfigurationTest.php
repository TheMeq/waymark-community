<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Actions\SaveHomepageSection;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\HomepageConfigurationSnapshot;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\HomepageSectionResource\Pages\ListHomepageSections;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class HomepageConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_are_guardrailed_ordered_and_schedule_aware(): void
    {
        HomepageSection::query()->create($this->section(['section_key' => 'gallery', 'sort_order' => 30]));
        HomepageSection::query()->create($this->section(['section_key' => 'hero', 'sort_order' => 10]));
        HomepageSection::query()->create($this->section(['section_key' => 'join', 'sort_order' => 20, 'visible_from' => now()->addHour()]));
        HomepageSection::query()->create($this->section(['section_key' => 'news', 'sort_order' => 40, 'enabled' => false]));

        $this->assertSame(['hero', 'gallery'], app(VisibleHomepageSections::class)->get()->pluck('section_key')->all());

        $this->expectException(ValidationException::class);
        HomepageSection::query()->create($this->section(['section_key' => 'arbitrary_builder', 'layout_variant' => 'anything']));
    }

    public function test_empty_configuration_falls_back_to_the_approved_composition(): void
    {
        $sections = app(VisibleHomepageSections::class)->get();

        $this->assertSame(['hero', 'whats_on', 'gallery', 'join'], $sections->pluck('section_key')->all());
        $this->assertSame('automatic', $sections->firstWhere('section_key', 'whats_on')->content_mode);
        $this->assertSame('message', $sections->firstWhere('section_key', 'gallery')->empty_behavior);
    }

    public function test_saving_a_section_records_the_previous_value_snapshot(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $section = HomepageSection::query()->create($this->section(['section_key' => 'gallery', 'heading' => 'Recent adventures']));

        app(SaveHomepageSection::class)->handle($administrator, $section, ['heading' => 'From our community', 'sort_order' => 15]);

        $this->assertSame('From our community', $section->fresh()->heading);
        $snapshot = HomepageConfigurationSnapshot::query()->sole();
        $this->assertSame('Recent adventures', $snapshot->previous_values['heading']);
        $this->assertSame('From our community', $snapshot->new_values['heading']);
        $this->assertSame($administrator->id, $snapshot->actor_id);
    }

    public function test_disabled_sections_are_not_rendered_and_copy_fields_are_applied(): void
    {
        HomepageSection::query()->create($this->section(['section_key' => 'gallery', 'enabled' => false]));
        HomepageSection::query()->create($this->section(['section_key' => 'whats_on', 'heading' => 'Coming up', 'cta_label' => 'Browse everything']));

        $this->get('/')->assertOk()
            ->assertSeeText('Coming up')
            ->assertSeeText('Browse everything')
            ->assertDontSeeText('Photos from our walks & holidays');
    }

    public function test_site_identity_and_all_section_presentation_fields_are_applied(): void
    {
        app(UpdateSiteProfile::class)->handle(['group_name' => 'Peak Pathfinders']);
        HomepageSection::query()->create($this->section([
            'section_key' => 'hero',
            'layout_variant' => 'compact',
            'heading' => 'Walk further together',
            'supporting_copy' => 'Friendly days outside.',
            'cta_label' => 'Choose a walk',
            'cta_url' => '/walks?featured=1',
        ]));

        $this->get('/')->assertOk()
            ->assertSeeText('Peak Pathfinders')
            ->assertSeeText('Walk further together')
            ->assertSeeText('Friendly days outside.')
            ->assertSeeText('Choose a walk')
            ->assertSee('href="/walks?featured=1"', false)
            ->assertSee('data-homepage-section="hero"', false)
            ->assertSee('data-layout="compact"', false);
    }

    public function test_gallery_empty_behaviour_and_fields_are_operational(): void
    {
        $section = HomepageSection::query()->create($this->section([
            'section_key' => 'gallery',
            'layout_variant' => 'feature_first',
            'heading' => 'Trail memories',
            'supporting_copy' => 'Shared by members.',
            'cta_label' => 'See every photo',
            'cta_url' => '/photos?all=1',
            'empty_behavior' => 'hide',
        ]));

        $this->get('/')->assertOk()->assertDontSeeText('Trail memories');

        $section->update(['empty_behavior' => 'message']);
        $this->get('/')->assertOk()
            ->assertSeeText('Trail memories')
            ->assertSeeText('Shared by members.')
            ->assertSeeText('See every photo')
            ->assertSee('href="/photos?all=1"', false)
            ->assertSee('data-layout="feature_first"', false);
    }

    public function test_configured_holiday_and_testimonial_are_independent_sections(): void
    {
        HomepageSection::query()->create($this->section(['section_key' => 'whats_on', 'sort_order' => 10]));
        HomepageSection::query()->create($this->section(['section_key' => 'holiday', 'sort_order' => 20, 'heading' => 'Trips away', 'empty_behavior' => 'message']));
        HomepageSection::query()->create($this->section(['section_key' => 'join', 'sort_order' => 30]));
        HomepageSection::query()->create($this->section(['section_key' => 'testimonial', 'sort_order' => 40, 'heading' => 'Member voices', 'empty_behavior' => 'message']));

        $content = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($content);
        $this->assertSame(1, substr_count($content, 'data-homepage-section="holiday"'));
        $this->assertSame(1, substr_count($content, 'data-homepage-section="testimonial"'));
        $this->assertStringContainsString('Trips away', $content);
        $this->assertStringContainsString('Member voices', $content);
    }

    public function test_manual_news_pin_is_used_first_and_missing_pin_falls_back_to_automatic_selection(): void
    {
        $author = User::factory()->create();
        $automatic = NewsArticle::query()->create($this->article($author, ['title' => 'Automatic update', 'slug' => 'automatic-update', 'featured_on_homepage' => true]));
        $pinned = NewsArticle::query()->create($this->article($author, ['title' => 'Pinned update', 'slug' => 'pinned-update', 'publish_at' => now()->subDay()]));
        $section = HomepageSection::query()->create($this->section([
            'section_key' => 'news',
            'content_mode' => 'pinned',
            'pinned_type' => 'news_article',
            'pinned_id' => $pinned->id,
        ]));

        $this->get('/')->assertOk()->assertSeeInOrder([$pinned->title, $automatic->title]);

        $section->update(['pinned_id' => 999999]);
        $this->get('/')->assertOk()->assertSeeText($automatic->title);
    }

    public function test_hero_join_and_testimonial_pins_use_only_publicly_eligible_content(): void
    {
        Storage::fake('local');
        $author = User::factory()->create();
        $path = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';
        Storage::disk('local')->put($path, 'image');
        $media = SiteMedia::query()->create(['created_by_user_id' => $author->id, 'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300', 'storage_disk' => 'local', 'processed_variants' => ['master' => $path], 'mime_type' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'file_size_bytes' => 5, 'alt_text' => 'Members on a summit', 'is_decorative' => false, 'focal_point_x' => .74, 'focal_point_y' => .28, 'processing_status' => 'complete', 'health_status' => 'healthy']);
        $page = CmsPage::query()->create(['title' => 'Become a member', 'slug' => 'become-a-member', 'blocks' => [['type' => 'rich_text', 'content' => '<p>Welcome.</p>']], 'publication_state' => 'published', 'publish_at' => now()]);
        $testimonial = Testimonial::query()->create(['quote' => 'Pinned member voice', 'display_name' => 'Alex', 'active' => true, 'sort_order' => 20]);

        HomepageSection::query()->create($this->section(['section_key' => 'hero', 'content_mode' => 'pinned', 'pinned_type' => 'site_media', 'pinned_id' => $media->id]));
        HomepageSection::query()->create($this->section(['section_key' => 'join', 'sort_order' => 20, 'content_mode' => 'pinned', 'pinned_type' => 'cms_page', 'pinned_id' => $page->id]));
        HomepageSection::query()->create($this->section(['section_key' => 'testimonial', 'sort_order' => 30, 'content_mode' => 'pinned', 'pinned_type' => 'testimonial', 'pinned_id' => $testimonial->id]));

        $this->get('/')->assertOk()
            ->assertSee(route('site-media.stream', [$media, 'master']), false)
            ->assertSee('alt="Members on a summit"', false)
            ->assertSee('style="object-position: 74% 28%"', false)
            ->assertSeeText('Become a member')
            ->assertSee(route('cms.show', $page->slug), false)
            ->assertSeeText('Pinned member voice');

        $page->update(['publication_state' => 'draft']);
        $testimonial->update(['active' => false]);
        $this->get('/')->assertOk()->assertDontSeeText('Become a member')->assertDontSeeText('Pinned member voice');
    }

    public function test_walk_and_holiday_pins_prefer_an_eligible_item_and_reject_a_private_item(): void
    {
        $walk = Event::factory()->create(['type' => EventType::Walk, 'title' => 'Pinned weekday walk', 'slug' => 'pinned-weekday-walk', 'starts_at' => now()->next('Wednesday')->addWeek(), 'ends_at' => now()->next('Wednesday')->addWeek()->addHours(4), 'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()]);
        Walk::query()->create(['event_id' => $walk->id, 'primary_leader_id' => $walk->organiser_id, 'meeting_location_name' => 'Hill gate']);
        $holiday = Event::factory()->create(['type' => EventType::Holiday, 'title' => 'Pinned coast break', 'slug' => 'pinned-coast-break', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDays(3), 'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()]);
        Holiday::query()->create(['event_id' => $holiday->id, 'destination' => 'The coast']);

        HomepageSection::query()->create($this->section(['section_key' => 'whats_on', 'content_mode' => 'pinned', 'pinned_type' => 'event', 'pinned_id' => $walk->id]));
        HomepageSection::query()->create($this->section(['section_key' => 'holiday', 'sort_order' => 20, 'content_mode' => 'pinned', 'pinned_type' => 'event', 'pinned_id' => $holiday->id]));

        $this->get('/')->assertOk()->assertSeeText('Pinned weekday walk')->assertSeeText('Pinned coast break');

        $walk->update(['is_public' => false]);
        $holiday->update(['status' => EventStatus::Draft, 'is_public' => false, 'published_at' => null]);
        $this->get('/')->assertOk()->assertDontSeeText('Pinned weekday walk')->assertDontSeeText('Pinned coast break');
    }

    public function test_homepage_empty_state_can_hide_or_show_a_concise_message(): void
    {
        $section = HomepageSection::query()->create($this->section(['section_key' => 'news', 'heading' => 'Latest news', 'empty_behavior' => 'hide']));

        $this->get('/')->assertOk()->assertDontSeeText('Latest news')->assertDontSeeText('No current news.');

        $section->update(['empty_behavior' => 'message']);
        $this->get('/')->assertOk()->assertSeeText('Latest news')->assertSeeText('No current news.');
    }

    public function test_pinned_content_type_is_constrained_per_approved_section(): void
    {
        $this->expectException(ValidationException::class);
        HomepageSection::query()->create($this->section([
            'section_key' => 'news',
            'content_mode' => 'pinned',
            'pinned_type' => 'arbitrary_model',
            'pinned_id' => 1,
        ]));
    }

    public function test_homepage_configuration_admin_is_content_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        $this->actingAs($administrator)->get('/admin/homepage-sections')->assertOk()->assertSeeText('Homepage');
        $this->actingAs($member)->get('/admin/homepage-sections')->assertForbidden();
    }

    public function test_content_manager_can_drag_reorder_all_sections_with_snapshots_and_public_order_persistence(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $gallery = HomepageSection::query()->create($this->section([
            'section_key' => 'gallery',
            'sort_order' => 30,
            'enabled' => false,
            'empty_behavior' => 'message',
        ]));
        $hero = HomepageSection::query()->create($this->section(['section_key' => 'hero', 'sort_order' => 10]));
        $join = HomepageSection::query()->create($this->section([
            'section_key' => 'join',
            'sort_order' => 20,
            'visible_from' => now()->addHour(),
        ]));

        $this->actingAs($administrator);
        Livewire::test(ListHomepageSections::class)
            ->call('reorderTable', [$gallery->id, $hero->id, $join->id]);

        $this->assertSame(1, $gallery->fresh()->sort_order);
        $this->assertSame(2, $hero->fresh()->sort_order);
        $this->assertSame(3, $join->fresh()->sort_order);
        $this->assertCount(3, HomepageConfigurationSnapshot::query()->get());
        $gallerySnapshot = HomepageConfigurationSnapshot::query()->where('homepage_section_id', $gallery->id)->sole();
        $this->assertSame($administrator->id, $gallerySnapshot->actor_id);
        $this->assertSame(30, $gallerySnapshot->previous_values['sort_order']);
        $this->assertSame(1, $gallerySnapshot->new_values['sort_order']);

        $gallery->update(['enabled' => true]);
        $join->update(['visible_from' => now()->subMinute()]);

        $this->assertSame(
            ['gallery', 'hero', 'join'],
            app(VisibleHomepageSections::class)->get()->pluck('section_key')->all(),
        );
        $this->get('/')->assertOk()
            ->assertSee('data-homepage-section="gallery"', false)
            ->assertSee('style="order: 1"', false)
            ->assertSee('style="order: 2"', false)
            ->assertSee('style="order: 3"', false);
    }

    /** @param array<string, mixed> $overrides */
    private function section(array $overrides = []): array
    {
        return array_merge([
            'section_key' => 'hero',
            'enabled' => true,
            'sort_order' => 10,
            'layout_variant' => 'default',
            'content_mode' => 'automatic',
            'empty_behavior' => 'hide',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function article(User $author, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Group update',
            'slug' => 'group-update',
            'summary' => 'A short community update.',
            'blocks' => [['type' => 'rich_text', 'content' => '<p>News body.</p>']],
            'author_id' => $author->id,
            'primary_category' => 'Community',
            'publication_state' => 'published',
            'publish_at' => now()->subHour(),
            'featured_on_homepage' => false,
        ], $overrides);
    }
}
