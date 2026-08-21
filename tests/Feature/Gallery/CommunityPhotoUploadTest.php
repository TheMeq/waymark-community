<?php

namespace Tests\Feature\Gallery;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\UploadCommunityPhoto;
use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageMetadata;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Socials\Models\Social;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CommunityPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_upload_page_requires_an_authenticated_account(): void
    {
        $this->get(route('community-photos.upload.create'))
            ->assertRedirect(route('login'));
    }

    public function test_direct_upload_page_prioritises_recent_events_and_offers_special_albums(): void
    {
        $account = User::factory()->create();
        $recent = $this->uploadableEvent([
            'type' => EventType::Walk,
            'title' => 'Yesterday on the ridge',
            'starts_at' => now()->subDay(),
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subMinute(),
        ]);
        $upcoming = $this->uploadableEvent([
            'type' => EventType::Social,
            'title' => 'Next month social',
            'starts_at' => now()->addMonth(),
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subMinute(),
        ]);
        $album = SpecialAlbum::query()->create([
            'title' => 'Volunteer day',
            'slug' => 'volunteer-day',
        ]);

        $response = $this->actingAs($account)->get(route('community-photos.upload.create'));

        $response->assertOk()
            ->assertSee('Share photos')
            ->assertSee($recent->title)
            ->assertSee($upcoming->title)
            ->assertSee($album->title)
            ->assertSeeInOrder([$recent->title, $upcoming->title])
            ->assertSee('name="photos[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('method="post"', false);
    }

    public function test_only_publicly_published_events_can_be_selected_or_mutated_as_photo_contexts(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $public = $this->uploadableEvent(['title' => 'Published event']);
        $draft = Event::factory()->create(['title' => 'Draft event']);
        $private = Event::factory()->create(['title' => 'Private event', 'status' => EventStatus::Published, 'is_public' => false, 'published_at' => now()->subMinute()]);

        $this->actingAs($account)->get(route('community-photos.upload.create', ['event' => $draft->id]))
            ->assertDontSee($draft->title)->assertDontSee($private->title)->assertSee($public->title)->assertDontSee('value="event:'.$draft->id.'" selected', false);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), ['context' => 'event:'.$private->id, 'accept_photo_policy' => true, 'photos' => [$this->pngUpload('private.png')]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_incomplete_public_events_of_every_type_are_rejected_as_upload_contexts(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();

        foreach (EventType::cases() as $type) {
            $event = Event::factory()->create(['type' => $type, 'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subMinute()]);
            $this->actingAs($account)->postJson(route('community-photos.upload.store'), ['context' => 'event:'.$event->id, 'accept_photo_policy' => true, 'photos' => [$this->pngUpload($type->value.'.png')]])->assertUnprocessable();
        }

        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_public_holiday_child_event_with_its_own_walk_record_is_uploadable(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $holiday = $this->uploadableEvent(['type' => EventType::Holiday]);
        $child = $this->uploadableEvent(['type' => EventType::Walk, 'parent_event_id' => $holiday->id]);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$child->id, 'accept_photo_policy' => true, 'photos' => [$this->pngUpload('holiday-child.png')],
        ])->assertCreated();

        $this->assertDatabaseHas('community_photos', ['event_id' => $child->id]);
    }

    public function test_completed_public_event_with_its_matching_extension_is_uploadable(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent(['type' => EventType::Social, 'status' => EventStatus::Completed]);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), ['context' => 'event:'.$event->id, 'accept_photo_policy' => true, 'photos' => [$this->pngUpload('completed.png')]])->assertCreated();
        $this->assertDatabaseHas('community_photos', ['event_id' => $event->id]);
    }

    public function test_completed_walk_social_and_holiday_contexts_remain_available_at_every_upload_boundary(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $walk = $this->uploadableEvent(['type' => EventType::Walk, 'status' => EventStatus::Completed, 'title' => 'Completed walk']);
        $social = $this->uploadableEvent(['type' => EventType::Social, 'status' => EventStatus::Completed, 'title' => 'Completed social']);
        $holiday = $this->uploadableEvent(['type' => EventType::Holiday, 'status' => EventStatus::Completed, 'title' => 'Completed holiday']);

        $this->actingAs($account)->get(route('community-photos.upload.create'))->assertSee($walk->title)->assertSee($social->title)->assertSee($holiday->title);
        $this->actingAs($account)->get(route('community-photos.upload.create', ['event' => $social->id]))->assertSee('value="event:'.$social->id.'" selected', false);
        $this->actingAs($account)->postJson(route('community-photos.upload.store'), ['context' => 'event:'.$holiday->id, 'accept_photo_policy' => true, 'photos' => [$this->pngUpload('completed-holiday.png')]])->assertCreated();
    }

    public function test_archived_public_events_are_excluded_from_the_chooser_preselection_and_mutation(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $archived = $this->uploadableEvent([
            'type' => EventType::Walk,
            'status' => EventStatus::Archived,
            'title' => 'Archived ridge walk',
        ]);

        $this->actingAs($account)->get(route('community-photos.upload.create'))
            ->assertDontSee($archived->title);
        $this->actingAs($account)->get(route('community-photos.upload.create', ['event' => $archived->id]))
            ->assertDontSee('value="event:'.$archived->id.'" selected', false);
        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$archived->id,
            'accept_photo_policy' => true,
            'photos' => [$this->pngUpload('archived.png')],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_named_photo_upload_route_returns_429_before_processing_after_its_limit(): void
    {
        RateLimiter::for('photo-upload', fn ($request) => [Limit::perMinute(1)->by('account:'.$request->user()->id), Limit::perMinute(20)->by('ip:'.$request->ip())]);
        $account = User::factory()->create();
        $payload = ['context' => 'event:999', 'accept_photo_policy' => true, 'photos' => [$this->pngUpload('limited.png')]];

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), $payload)->assertUnprocessable();
        $this->actingAs($account)->postJson(route('community-photos.upload.store'), $payload)->assertStatus(429);
        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_html_fallback_renders_individual_mixed_batch_outcomes_after_redirect(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent();

        $response = $this->actingAs($account)->from(route('community-photos.upload.create'))->post(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id, 'accept_photo_policy' => true,
            'photos' => [$this->pngUpload('submitted.png'), UploadedFile::fake()->createWithContent('failed.png', 'bad')->mimeType('image/png')],
        ]);

        $response->assertRedirect();
        $this->followRedirects($response)->assertSee('submitted.png')->assertSee('Submitted')->assertSee('failed.png')->assertSee('Not uploaded')->assertSee('autofocus', false);
        $this->assertDatabaseCount('community_photos', 1);
    }

    public function test_verified_active_account_can_upload_one_photo_to_an_event_after_accepting_the_current_policy(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create(['display_name' => 'Taylor W.']);
        $event = $this->uploadableEvent(['type' => EventType::Walk]);

        $response = $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id,
            'accept_photo_policy' => true,
            'photos' => [$this->pngUpload('ridge.png')],
        ]);

        $response->assertCreated()
            ->assertJsonPath('photos.0.status', 'uploaded');
        $this->assertDatabaseHas('photo_policy_acceptances', [
            'user_id' => $account->id,
            'policy_version' => (string) config('gallery.photo_policy.current_version'),
        ]);
        $this->assertDatabaseHas('community_photos', [
            'event_id' => $event->id,
            'uploader_id' => $account->id,
            'photographer_name' => 'Taylor W.',
            'processing_status' => 'complete',
            'moderation_status' => 'pending',
        ]);
        $this->assertSame(1, CommunityPhoto::query()->count());
    }

    public function test_upload_requires_the_current_policy_when_no_explicit_acceptance_is_supplied(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent();

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id,
            'photos' => [$this->pngUpload('policy.png')],
        ])->assertUnprocessable()
            ->assertJsonPath('photos.0.status', 'failed')
            ->assertJsonPath('photos.0.errors.0', 'Accept the current photo policy before uploading photos.');

        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_upload_rejects_a_dual_context_supplied_outside_the_normal_context_chooser(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent();
        $album = SpecialAlbum::query()->create(['title' => 'Committee archive', 'slug' => 'committee-archive']);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id,
            'event_id' => $event->id,
            'special_album_id' => $album->id,
            'accept_photo_policy' => true,
            'photos' => [$this->pngUpload('dual-context.png')],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id', 'special_album_id']);

        $this->assertDatabaseCount('community_photos', 0);
    }

    public function test_mixed_batch_keeps_the_successful_photo_when_another_file_fails_and_retry_only_creates_the_replacement(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent();

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id,
            'accept_photo_policy' => true,
            'photos' => [
                $this->pngUpload('kept.png'),
                UploadedFile::fake()->createWithContent('broken.png', 'not a raster')->mimeType('image/png'),
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('photos.0.status', 'uploaded')
            ->assertJsonPath('photos.1.status', 'failed');

        $this->assertDatabaseCount('community_photos', 1);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'event:'.$event->id,
            'photos' => [$this->pngUpload('replacement.png')],
        ])->assertCreated()
            ->assertJsonPath('photos.0.status', 'uploaded');

        $this->assertDatabaseCount('community_photos', 2);
    }

    public function test_upload_uses_the_explicit_photographer_credit_for_a_special_album(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create(['display_name' => 'Taylor W.']);
        $album = SpecialAlbum::query()->create(['title' => 'Committee archive', 'slug' => 'committee-archive']);

        $this->actingAs($account)->postJson(route('community-photos.upload.store'), [
            'context' => 'album:'.$album->id,
            'accept_photo_policy' => true,
            'photographer_name' => 'Jordan P.',
            'caption' => 'A bright afternoon.',
            'photos' => [$this->pngUpload('album.png')],
        ])->assertCreated();

        $this->assertDatabaseHas('community_photos', [
            'special_album_id' => $album->id,
            'event_id' => null,
            'uploader_id' => $account->id,
            'photographer_name' => 'Jordan P.',
            'caption' => 'A bright afternoon.',
        ]);
    }

    public function test_persistence_failure_removes_only_the_processed_files_for_that_upload(): void
    {
        Storage::fake('local');
        $this->useUploadProcessorDouble();
        $account = User::factory()->create();
        $event = $this->uploadableEvent();
        $failPersistence = true;

        CommunityPhoto::creating(function () use (&$failPersistence): void {
            if ($failPersistence) {
                throw new \RuntimeException('Database write failed.');
            }
        });

        try {
            app(UploadCommunityPhoto::class)->handle(
                $account,
                $this->pngUpload('persistence-failure.png'),
                'event:'.$event->id,
                null,
                null,
                true,
            );
            $this->fail('The persistence failure was not rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Database write failed.', $exception->getMessage());
        } finally {
            $failPersistence = false;
        }

        $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        $this->assertDatabaseCount('community_photos', 0);
    }

    private function useUploadProcessorDouble(): void
    {
        app()->bind(RasterImageTransformer::class, fn (): UploadTestRasterTransformer => new UploadTestRasterTransformer);
        app()->bind(ImageMetadataReader::class, fn (): UploadTestMetadataReader => new UploadTestMetadataReader);
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL82QAAAABJRU5ErkJggg==', true),
        )->mimeType('image/png');
    }

    private function uploadableEvent(array $attributes = []): Event
    {
        $event = Event::factory()->create(array_replace([
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));

        match ($event->type) {
            EventType::Walk => Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $event->organiser_id]),
            EventType::Social => Social::query()->create(['event_id' => $event->id]),
            EventType::Holiday => Holiday::query()->create(['event_id' => $event->id]),
        };

        return $event;
    }
}

final class UploadTestRasterTransformer implements RasterImageTransformer
{
    public function supportsInput(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png'], true);
    }

    public function supportsOutput(string $mimeType): bool
    {
        return $mimeType === 'image/jpeg';
    }

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
    {
        return new UploadTestDecodedRaster;
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        return new TransformedRasterImage('safe-raster', 1, 1, $mimeType);
    }
}

final class UploadTestDecodedRaster implements DecodedRasterImage
{
    public function width(): int
    {
        return 1;
    }

    public function height(): int
    {
        return 1;
    }

    public function release(): void {}
}

final class UploadTestMetadataReader implements ImageMetadataReader
{
    public function read(string $path, string $mimeType): ImageMetadata
    {
        return new ImageMetadata(1, null);
    }
}
