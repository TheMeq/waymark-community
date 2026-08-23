<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Actions\SaveHomepageSection;
use App\Domain\Content\Models\HomepageConfigurationSnapshot;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        $this->assertSame('hide', $sections->firstWhere('section_key', 'gallery')->empty_behavior);
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
