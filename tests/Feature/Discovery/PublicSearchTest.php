<?php

namespace Tests\Feature\Discovery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Governance\Actions\PublishDocumentVersion;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_each_approved_public_content_type_with_case_insensitive_partial_matching(): void
    {
        $this->publishedWalk('Moorland Ridge Circuit', 'Stanage Moor', 'Moderate');
        CmsPage::query()->create($this->page(['title' => 'Moorland kit guide', 'slug' => 'moorland-kit']));
        NewsArticle::query()->create($this->article(['title' => 'Moorland path update', 'slug' => 'moorland-news']));
        $this->publishedDocument('Moorland safety policy', 'moorland-policy', 'Policies');
        $this->publicAlbum('Moorland memories', 'moorland-memories');

        $response = $this->get('/search?q=MOOR');

        $response->assertOk()
            ->assertSeeText('Moorland Ridge Circuit')
            ->assertSeeText('Moorland kit guide')
            ->assertSeeText('Moorland path update')
            ->assertSeeText('Moorland safety policy')
            ->assertSeeText('Moorland memories');
    }

    public function test_search_never_returns_draft_private_future_expired_restricted_or_empty_album_content(): void
    {
        $this->publishedWalk('Visible Beacon Walk', 'Beacon Hill', 'Moderate');
        $this->publishedWalk('Private Beacon Walk', 'Beacon Hill', 'Moderate', ['is_public' => false]);
        $this->publishedWalk('Draft Beacon Walk', 'Beacon Hill', 'Moderate', ['status' => EventStatus::Draft, 'published_at' => null]);
        CmsPage::query()->create($this->page(['title' => 'Draft Beacon Page', 'slug' => 'draft-beacon', 'publication_state' => 'draft']));
        CmsPage::query()->create($this->page(['title' => 'Future Beacon Page', 'slug' => 'future-beacon', 'publish_at' => now()->addDay()]));
        NewsArticle::query()->create($this->article(['title' => 'Expired Beacon News', 'slug' => 'expired-beacon', 'expires_at' => now()->subMinute()]));
        $this->publishedDocument('Restricted Beacon Policy', 'restricted-beacon', 'Policies', ['visibility' => 'registered']);
        SpecialAlbum::query()->create(['title' => 'Empty Beacon Album', 'slug' => 'empty-beacon']);

        $response = $this->get('/search?q=beacon');

        $response->assertOk()->assertSeeText('Visible Beacon Walk');
        foreach (['Private Beacon Walk', 'Draft Beacon Walk', 'Draft Beacon Page', 'Future Beacon Page', 'Expired Beacon News', 'Restricted Beacon Policy', 'Empty Beacon Album'] as $hidden) {
            $response->assertDontSeeText($hidden);
        }
    }

    public function test_search_filters_content_type_date_difficulty_location_and_document_category(): void
    {
        $matching = $this->publishedWalk('Filtered Ridge', 'North Moor', 'Challenging', ['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(4)]);
        $this->publishedWalk('Wrong Place', 'South Valley', 'Easy', ['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(4)]);
        $policy = $this->publishedDocument('Filtered walking policy', 'filtered-policy', 'Policies');
        $this->publishedDocument('Filtered meeting minutes', 'filtered-minutes', 'Minutes');

        $this->get('/search?type=event&date_from='.now()->addDays(4)->toDateString().'&date_to='.now()->addDays(6)->toDateString().'&difficulty=Challenging&location=north')
            ->assertOk()->assertSeeText($matching->title)->assertDontSeeText('Wrong Place')->assertDontSeeText($policy->title);

        $this->get('/search?q=filtered&type=document&document_category=policies')
            ->assertOk()->assertSeeText($policy->title)->assertDontSeeText('Filtered meeting minutes')->assertDontSeeText($matching->title);
    }

    private function publishedWalk(string $title, string $location, string $gradeName, array $eventOverrides = []): Event
    {
        $leader = User::factory()->create();
        $grade = Grade::query()->firstOrCreate(['name' => $gradeName], ['display_order' => 10, 'description' => $gradeName.' walks']);
        $event = Event::factory()->for($leader, 'organiser')->create(array_merge([
            'type' => EventType::Walk,
            'title' => $title,
            'slug' => str($title)->slug(),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subHour(),
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
        ], $eventOverrides));
        Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $leader->id, 'grade_id' => $grade->id, 'meeting_location_name' => $location]);

        return $event;
    }

    private function publicAlbum(string $title, string $slug): void
    {
        $album = SpecialAlbum::query()->create(['title' => $title, 'slug' => $slug, 'description' => 'Public album']);
        $directory = 'community-photos/'.Str::uuid();
        CommunityPhoto::query()->create([
            'special_album_id' => $album->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'storage_disk' => 'local',
            'source_path' => $directory.'/source.jpg',
            'processed_variants' => ['thumbnail' => $directory.'/thumbnail.jpg'],
            'file_size_bytes' => 100,
            'moderation_status' => 'approved',
            'processing_status' => 'complete',
            'published_at' => now()->subMinute(),
        ]);
    }

    private function publishedDocument(string $title, string $slug, string $categoryName, array $overrides = []): Document
    {
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $category = DocumentCategory::query()->firstOrCreate(['name' => $categoryName], ['slug' => str($categoryName)->slug(), 'sort_order' => 10]);
        $document = Document::query()->create(array_merge([
            'document_category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Public guidance',
            'visibility' => 'public',
            'publication_date' => today(),
            'controlled' => false,
            'approval_status' => 'approved',
        ], $overrides));
        $version = $document->versions()->create([
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'/v1.pdf',
            'original_filename' => "{$slug}.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'created_by_user_id' => $actor->id,
        ]);
        app(PublishDocumentVersion::class)->handle($actor, $document, $version);

        return $document;
    }

    private function page(array $overrides = []): array
    {
        return array_merge(['title' => 'Public page', 'slug' => 'public-page', 'blocks' => [['type' => 'rich_text', 'content' => '<p>Public content</p>']], 'publication_state' => 'published', 'publish_at' => now()->subMinute()], $overrides);
    }

    private function article(array $overrides = []): array
    {
        return array_merge(['title' => 'Public news', 'slug' => 'public-news', 'summary' => 'Public update', 'blocks' => [['type' => 'rich_text', 'content' => '<p>Public news</p>']], 'author_id' => User::factory()->create()->id, 'primary_category' => 'News', 'publication_state' => 'published', 'publish_at' => now()->subMinute()], $overrides);
    }
}
