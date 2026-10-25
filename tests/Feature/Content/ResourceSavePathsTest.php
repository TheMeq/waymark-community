<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\CmsPage;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Socials\Models\Social;
use App\Filament\Resources\CmsPageResource\Pages\CreateCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Filament\Resources\HolidayResource\Pages\CreateHoliday;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Filament\Resources\SocialResource\Pages\CreateSocial;
use App\Filament\Resources\SocialResource\Pages\EditSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ResourceSavePathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_domain_validation_is_visible_on_the_correct_fields(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateHoliday::class)->fillForm([
            'title' => 'Coastal holiday', 'starts_at' => '2026-11-06 16:00:00',
            'ends_at' => '2026-11-09 10:00:00', 'pricing_type' => 'fixed',
        ])->call('create')->assertHasFormErrors(['price_amount', 'currency'])
            ->assertNotified('Please check the form');
        $this->assertDatabaseCount('holidays', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_social_domain_date_validation_is_visible_and_a_retry_can_save(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateSocial::class)->fillForm([
            'title' => 'Supper', 'starts_at' => '2026-11-06 19:00:00', 'ends_at' => '2026-11-06 18:00:00',
        ])->call('create')->assertHasFormErrors(['ends_at'])->assertNotified('Please check the form')
            ->fillForm(['ends_at' => '2026-11-06 22:00:00'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('socials', 1);
    }

    public function test_page_builder_data_is_saved_and_can_be_edited(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateCmsPage::class)->fillForm([
            'title' => 'Welcome', 'slug' => 'welcome', 'publication_state' => 'draft',
            'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>Welcome walkers.</p>']]],
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('cms_pages', 1);
        $page = CmsPage::query()->sole();
        $this->assertSame('<p>Welcome walkers.</p>', $page->blocks[0]['content']);
        Livewire::test(EditCmsPage::class, ['record' => $page->id])
            ->call('save')->assertHasNoFormErrors();
    }

    public function test_news_builder_data_is_saved(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateNewsArticle::class)->fillForm([
            'title' => 'Group news', 'slug' => 'group-news', 'author_id' => $administrator->id,
            'primary_category' => 'Community', 'publication_state' => 'draft',
            'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>Latest news.</p>']]],
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('news_articles', 1);
    }

    public function test_holiday_create_edit_and_publish_have_visible_confirmation(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateHoliday::class)->fillForm([
            'title' => 'Coast break', 'starts_at' => '2026-11-06 16:00:00', 'ends_at' => '2026-11-09 10:00:00',
            'pricing_type' => 'fixed', 'price_amount' => 100, 'currency' => 'GBP',
        ])->call('create')->assertHasNoFormErrors()->assertNotified('Holiday draft created');
        $holiday = Holiday::query()->sole();
        Livewire::test(EditHoliday::class, ['record' => $holiday->id])
            ->assertSee('Status: Draft')->fillForm(['destination' => 'Coast'])
            ->call('save')->assertHasNoFormErrors()->callAction('publish')
            ->assertNotified('Holiday published')->assertSee('Status: Published');
        $this->assertSame('Coast', $holiday->fresh()->destination);
    }

    public function test_social_create_edit_and_publish_have_visible_confirmation(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateSocial::class)->fillForm([
            'title' => 'Supper', 'starts_at' => '2026-11-06 19:00:00',
        ])->call('create')->assertHasNoFormErrors()->assertNotified('Social draft created');
        $social = Social::query()->sole();
        Livewire::test(EditSocial::class, ['record' => $social->id])
            ->assertSee('Status: Draft')->fillForm(['venue_name' => 'Village hall'])
            ->call('save')->assertHasNoFormErrors()->callAction('publish')
            ->assertNotified('Social published')->assertSee('Status: Published');
        $this->assertSame('Village hall', $social->fresh()->venue_name);
    }
}
