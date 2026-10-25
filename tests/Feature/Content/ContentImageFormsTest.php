<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\CmsPage;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\SiteMedia\ContentImagePresenter;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use App\Filament\Resources\CmsPageResource\Pages\CreateCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContentImageFormsTest extends TestCase
{
    use RefreshDatabase;

    public static function imageForms(): array
    {
        return [
            'holiday' => ['Holiday', 'App\\Domain\\Holidays\\Models\\Holiday', 'featured_image_media_id', 'featured_image_path', ['title' => 'Coast break', 'starts_at' => '2026-11-06 16:00:00', 'ends_at' => '2026-11-09 10:00:00']],
            'page' => ['CmsPage', 'App\\Domain\\Content\\Models\\CmsPage', 'hero_media_id', 'hero_image_path', ['title' => 'Welcome', 'slug' => 'welcome', 'publication_state' => 'draft', 'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>Welcome.</p>']]]]],
            'news' => ['NewsArticle', 'App\\Domain\\Content\\Models\\NewsArticle', 'featured_media_id', 'featured_image_path', ['title' => 'Group news', 'slug' => 'group-news', 'primary_category' => 'Community', 'publication_state' => 'draft', 'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p>News.</p>']]]]],
            'testimonial' => ['Testimonial', 'App\\Domain\\Content\\Models\\Testimonial', 'image_media_id', 'image_path', ['quote' => 'A lovely group.', 'display_name' => 'A walker', 'sort_order' => 0]],
            'committee' => ['CommitteeRole', 'App\\Domain\\Governance\\Models\\CommitteeRole', 'public_photo_media_id', 'public_photo_path', ['title' => 'Chair', 'public_name' => 'A walker', 'sort_order' => 0]],
        ];
    }

    #[DataProvider('imageForms')]
    public function test_image_upload_url_and_none_are_saved_and_previewed(string $resource, string $model, string $mediaColumn, string $pathColumn, array $attributes): void
    {
        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
        $administrator = User::factory()->initialAdministrator()->create();
        if ($resource === 'NewsArticle') {
            $attributes['author_id'] = $administrator->id;
        }
        $create = 'App\\Filament\\Resources\\'.$resource.'Resource\\Pages\\Create'.$resource;
        $edit = 'App\\Filament\\Resources\\'.$resource.'Resource\\Pages\\Edit'.$resource;
        Livewire::actingAs($administrator)->test($create)
            ->assertFormFieldVisible('content_image_source')
            ->fillForm([...$attributes, 'content_image_source' => 'managed',
                'content_image_upload' => UploadedFile::fake()->image('image.jpg', 1200, 600),
                'content_image_alt_text' => 'Walkers beside the coast',
            ])->call('create')->assertHasNoFormErrors();
        $record = $model::query()->sole();
        $this->assertNotNull($record->{$mediaColumn});
        $media = SiteMedia::query()->findOrFail($record->{$mediaColumn});
        $this->assertNull($media->orphaned_at);
        $this->assertTrue(app(SiteMediaUsage::class)->isUsed($media));
        $component = Livewire::test($edit, ['record' => $record->id])
            ->assertFormSet(['content_image_source' => 'managed'])->assertSee('Saved local image.');
        $savedId = $record->{$mediaColumn};
        $component->fillForm(['content_image_source' => 'external', 'content_image_url' => 'javascript:alert(1)'])
            ->call('save')->assertHasFormErrors(['content_image_url'])->assertNotified('Please check the form');
        $this->assertSame($savedId, $record->fresh()->{$mediaColumn});

        $component->fillForm(['content_image_source' => 'external', 'content_image_url' => 'https://images.example.org/coast.jpg'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->fresh()->{$mediaColumn});
        $this->assertSame('https://images.example.org/coast.jpg', $record->fresh()->{$pathColumn});
        $slot = ContentImageSlot::from($model);
        $this->assertSame('https://images.example.org/coast.jpg', app(ContentImagePresenter::class)->present($record->fresh(), $slot)?->url);

        $component = Livewire::test($edit, ['record' => $record->id])
            ->assertFormSet(['content_image_source' => 'external'])->assertSee('Saved external image.');
        $component->fillForm(['content_image_source' => 'none'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->fresh()->{$pathColumn});
        $this->assertNull(app(ContentImagePresenter::class)->present($record->fresh(), $slot));
        Livewire::test($edit, ['record' => $record->id])->assertFormSet(['content_image_source' => 'none']);
    }

    public function test_image_and_text_blocks_support_upload_url_and_none(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test(CreateCmsPage::class)
            ->fillForm([
                'title' => 'Block images', 'slug' => 'block-images', 'publication_state' => 'published',
                'blocks' => [['type' => 'image_text', 'data' => [
                    'heading' => 'Our walks', 'body' => 'Discover the coast.',
                    'content_image_source' => 'managed',
                    'content_image_alt_text' => 'The coastal path',
                ]]],
            ])->upload('data.blocks.0.data.content_image_upload', [UploadedFile::fake()->image('coast.jpg', 1200, 600)], true)
            ->call('create')->assertStatus(200)->assertHasNoFormErrors();
        $page = CmsPage::query()->sole();
        $media = SiteMedia::query()->findOrFail($page->blocks[0]['media_id']);
        $this->assertTrue(app(SiteMediaUsage::class)->isUsed($media));
        $this->assertNull($media->orphaned_at);
        $this->get(route('cms.show', $page->slug))->assertOk()->assertSee(route('site-media.stream', [$media, 'master']), false);
        $component = Livewire::test(EditCmsPage::class, ['record' => $page->id]);
        $component->fillForm(['blocks' => [['type' => 'image_text', 'data' => [
            'body' => 'Discover the coast.', 'media_id' => $media->id, 'content_image_source' => 'managed',
            'content_image_alt_text' => 'Updated coastal description',
        ]]]])->call('save')->assertHasNoFormErrors();
        $this->get(route('cms.show', $page->slug))->assertOk()->assertSee('alt="Updated coastal description"', false);
        $component->fillForm(['blocks' => [['type' => 'image_text', 'data' => [
            'body' => 'Discover the coast.', 'content_image_source' => 'external',
            'content_image_url' => 'https://images.example.org/coast.jpg', 'content_image_alt_text' => 'Coastal path',
        ]]]])->call('save')->assertHasNoFormErrors();
        $this->get(route('cms.show', $page->slug))->assertOk()->assertSee('https://images.example.org/coast.jpg', false);
        $this->assertNotNull($media->fresh()->orphaned_at);
        $component->fillForm(['blocks' => [['type' => 'image_text', 'data' => [
            'body' => 'Discover the coast.', 'content_image_source' => 'none',
        ]]]])->call('save')->assertHasNoFormErrors();
        $this->get(route('cms.show', $page->slug))->assertOk()->assertDontSee('https://images.example.org/coast.jpg', false);
    }

    #[DataProvider('imageForms')]
    public function test_saved_external_images_render_on_public_pages(string $resource, string $model, string $mediaColumn, string $pathColumn, array $attributes): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        if ($resource === 'NewsArticle') {
            $attributes['author_id'] = $administrator->id;
        }
        $create = 'App\\Filament\\Resources\\'.$resource.'Resource\\Pages\\Create'.$resource;
        Livewire::actingAs($administrator)->test($create)->fillForm([
            ...$attributes, 'content_image_source' => 'external', 'content_image_url' => 'https://images.example.org/public-coast.jpg',
            'content_image_alt_text' => 'The coastal path',
        ])->call('create')->assertHasNoFormErrors();
        $record = $model::query()->sole();
        if ($resource === 'Holiday') {
            $record->event->update(['is_public' => true, 'status' => EventStatus::Published, 'published_at' => now()]);
            $url = route('holidays.show', $record->event->slug);
        } elseif (in_array($resource, ['CmsPage', 'NewsArticle'], true)) {
            $record->update(['publication_state' => 'published', 'publish_at' => now()]);
            $url = route($resource === 'CmsPage' ? 'cms.show' : 'news.show', $record->slug);
        } else {
            $url = $resource === 'CommitteeRole' ? route('committee.index') : '/';
        }
        $this->get($url)->assertOk()->assertSee('src="https://images.example.org/public-coast.jpg"', false)
            ->assertSee('alt="The coastal path"', false);
    }

    public function test_existing_holiday_image_path_remains_editable_without_reentering_metadata(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $holiday = app(CreateHoliday::class)->handle($administrator, [
            'title' => 'Existing coast break', 'starts_at' => '2026-11-06 16:00:00', 'ends_at' => '2026-11-09 10:00:00',
            'featured_image_path' => '/images/demo/coastal-weekend.png',
        ]);
        Livewire::actingAs($administrator)->test(EditHoliday::class, ['record' => $holiday->id])
            ->assertFormSet(['content_image_source' => 'external'])
            ->fillForm(['destination' => 'Coast'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('/images/demo/coastal-weekend.png', $holiday->fresh()->featured_image_path);
    }
}
