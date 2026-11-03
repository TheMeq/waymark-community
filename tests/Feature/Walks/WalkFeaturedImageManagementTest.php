<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\SiteMedia\Actions\UploadSiteMedia;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Actions\UpdateWalkFeaturedImage;
use App\Domain\Walks\Data\WalkFeaturedImageInput;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class WalkFeaturedImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
    }

    public function test_walk_owner_uploads_without_media_library_access_and_alt_is_owned_by_walk(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);

        $updated = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('ridge.jpg'), alt: 'Walkers crossing a high ridge'),
        );

        $media = $updated->featuredMedia;
        $this->assertNotNull($media);
        $this->assertFalse($owner->hasCapability(ModuleCapability::ManageSiteMedia));
        $this->assertSame(SiteMediaPurpose::WalkFeaturedImage, $media->purpose);
        $this->assertFalse($media->is_decorative);
        $this->assertSame('Walkers crossing a high ridge', $media->alt_text);
        $this->assertSame('Walkers crossing a high ridge', $updated->featured_image_alt_text);
        $this->assertSame('/images/demo/woodland-walk.png', $updated->featured_image_path);
        $this->assertNull($media->orphaned_at);

        $uploaded = SiteMediaAudit::query()->where('site_media_id', $media->id)->where('action', 'uploaded')->sole();
        $attached = SiteMediaAudit::query()->where('site_media_id', $media->id)->where('action', 'attached')->sole();
        $this->assertSame(SiteMediaPurpose::WalkFeaturedImage->value, $uploaded->after['purpose']);
        $this->assertSame([
            'owner_type' => 'walk',
            'owner_id' => $walk->id,
            'slot' => 'featured_image',
        ], $attached->context);
        $this->assertStringNotContainsString('site-media/', json_encode($attached->context, JSON_THROW_ON_ERROR));

        try {
            app(UploadSiteMedia::class)->handle(
                $owner,
                $this->image('library-denied.jpg'),
                new SiteMediaMetadata('Library upload remains denied', false),
            );
            $this->fail('Walk image authority granted access to the Media library.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('site_media', 1);
        }
    }

    public function test_description_only_edit_changes_walk_alt_without_mutating_shared_media_metadata(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $walk = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('shared.jpg'), alt: 'Generic shared description'),
        );
        $media = $walk->featuredMedia;
        $other = $this->walk($owner, [
            'featured_image_media_id' => $media->id,
            'featured_image_alt_text' => 'Description for the other Walk',
        ]);

        $updated = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', alt: 'Description for this Walk only'),
        );

        $this->assertSame($media->id, $updated->featured_image_media_id);
        $this->assertSame('Description for this Walk only', $updated->featured_image_alt_text);
        $this->assertSame('Generic shared description', $media->fresh()->alt_text);
        $this->assertSame('Description for the other Walk', $other->fresh()->featured_image_alt_text);
    }

    public function test_unauthorised_accounts_fail_before_media_or_owner_mutation(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);
        $actors = [
            User::factory()->walkLeader()->create(),
            User::factory()->walkLeader()->create(['account_status' => AccountStatus::Suspended]),
            User::factory()->create(),
        ];

        foreach ($actors as $actor) {
            try {
                app(UpdateWalkFeaturedImage::class)->handle(
                    $actor,
                    $walk,
                    $this->input('managed', upload: $this->image('blocked.jpg'), alt: 'Blocked image'),
                );
                $this->fail('An unauthorised account changed a Walk image.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('site_media', 0);
                $this->assertSame('/images/demo/woodland-walk.png', $walk->fresh()->featured_image_path);
            }
        }

        $unverified = User::factory()->walkLeader()->unverified()->create();

        try {
            app(SaveWalkDraft::class)->create($unverified, [
                'title' => 'Unverified Walk',
                'starts_at' => '2026-09-12 09:00:00',
            ]);
            $this->fail('An unverified account created the initial Draft.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('site_media', 0);
            $this->assertDatabaseCount('walks', 1);
        }
    }

    public function test_stale_owner_state_is_refetched_and_rejected_before_upload_processing(): void
    {
        $formerOwner = User::factory()->walkLeader()->create();
        $currentOwner = User::factory()->walkLeader()->create();
        $walk = $this->walk($formerOwner);
        $staleWalk = $walk->fresh()->load('event');
        $walk->event->forceFill(['organiser_id' => $currentOwner->id])->save();

        try {
            app(UpdateWalkFeaturedImage::class)->handle(
                $formerOwner,
                $staleWalk,
                $this->input('managed', upload: $this->image('stale-owner.jpg'), alt: 'Blocked stale owner upload'),
            );
            $this->fail('Stale owner state reached Walk image processing.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('site_media', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
            $this->assertNull($walk->fresh()->featured_image_media_id);
        }
    }

    public function test_managed_external_and_none_transitions_preserve_fallback_and_orphan_only_unused_media(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);
        $walk = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('managed.jpg'), alt: 'Managed Walk image'),
        );
        $managed = $walk->featuredMedia;

        $external = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('external', external: 'https://images.example.org/new-walk.webp', alt: 'External Walk image'),
        );

        $this->assertNull($external->featured_image_media_id);
        $this->assertSame('https://images.example.org/new-walk.webp', $external->featured_image_path);
        $this->assertSame('External Walk image', $external->featured_image_alt_text);
        $this->assertNotNull($managed->fresh()->orphaned_at);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $managed->id, 'action' => 'detached']);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $managed->id, 'action' => 'orphaned']);
        foreach (['detached', 'orphaned'] as $action) {
            $context = SiteMediaAudit::query()
                ->where('site_media_id', $managed->id)
                ->where('action', $action)
                ->sole()
                ->context;
            $this->assertSame(['owner_type' => 'walk', 'owner_id' => $walk->id, 'slot' => 'featured_image'], $context);
            $this->assertStringNotContainsString('site-media/', json_encode($context, JSON_THROW_ON_ERROR));
        }

        $cleared = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $external,
            $this->input('none'),
        );
        $this->assertNull($cleared->featured_image_media_id);
        $this->assertNull($cleared->featured_image_path);
        $this->assertNull($cleared->featured_image_alt_text);
    }

    public function test_managed_removal_can_reveal_retained_fallback_or_clear_both_sources(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);
        $walk = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('managed.jpg'), alt: 'Managed Walk image'),
        );

        $revealed = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('external', external: '/images/demo/woodland-walk.png', alt: 'Walkers in woodland'),
        );
        $this->assertNull($revealed->featured_image_media_id);
        $this->assertSame('/images/demo/woodland-walk.png', $revealed->featured_image_path);

        $managedAgain = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $revealed,
            $this->input('managed', upload: $this->image('replacement.jpg'), alt: 'Replacement image'),
        );
        $cleared = app(UpdateWalkFeaturedImage::class)->handle($owner, $managedAgain, $this->input('none'));

        $this->assertNull($cleared->featured_image_media_id);
        $this->assertNull($cleared->featured_image_path);
        $this->assertNull($cleared->featured_image_alt_text);
    }

    public function test_shared_usage_prevents_orphaning_and_external_removal_does_not_touch_unrelated_media(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $first = $this->walk($owner);
        $first = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $first,
            $this->input('managed', upload: $this->image('shared.jpg'), alt: 'Shared media metadata'),
        );
        $media = $first->featuredMedia;
        $second = $this->walk($owner, [
            'featured_image_media_id' => $media->id,
            'featured_image_alt_text' => 'Second Walk description',
        ]);

        app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $first,
            $this->input('external', external: 'https://images.example.org/first.jpg', alt: 'First external image'),
        );
        $this->assertNull($media->fresh()->orphaned_at);

        $externalOnly = $this->walk($owner, [
            'featured_image_path' => 'https://images.example.org/fallback.jpg',
            'featured_image_alt_text' => 'An unrelated external image',
        ]);
        app(UpdateWalkFeaturedImage::class)->handle($owner, $externalOnly, $this->input('none'));

        $this->assertSame('Shared media metadata', $media->fresh()->alt_text);
        $this->assertSame('healthy', $media->fresh()->health_status);
        $this->assertNull($media->fresh()->orphaned_at);
        $this->assertSame($media->id, $second->fresh()->featured_image_media_id);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
    }

    public function test_failed_processing_or_owner_transaction_keeps_previous_image_atomic(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);
        $walk = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('original.jpg'), alt: 'Original image'),
        );
        $original = $walk->featuredMedia;

        try {
            app(UpdateWalkFeaturedImage::class)->handle(
                $owner,
                $walk,
                $this->input('managed', upload: UploadedFile::fake()->createWithContent('broken.jpg', 'not-an-image'), alt: 'Broken replacement'),
            );
            $this->fail('Malformed replacement processing succeeded.');
        } catch (ValidationException) {
            $this->assertSame($original->id, $walk->fresh()->featured_image_media_id);
            $this->assertSame('/images/demo/woodland-walk.png', $walk->fresh()->featured_image_path);
        }

        Walk::saving(static function (Walk $saving) use ($walk): void {
            if ($saving->id === $walk->id && $saving->isDirty('featured_image_media_id')) {
                throw new RuntimeException('Owner transaction failed.');
            }
        });

        try {
            app(UpdateWalkFeaturedImage::class)->handle(
                $owner,
                $walk->fresh(),
                $this->input('managed', upload: $this->image('transaction.jpg'), alt: 'Transaction replacement'),
            );
            $this->fail('A failed owner transaction changed the active image.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Owner transaction failed.', $exception->getMessage());
        } finally {
            Walk::flushEventListeners();
            Walk::clearBootedModels();
        }

        $this->assertSame($original->id, $walk->fresh()->featured_image_media_id);
        $this->assertSame('/images/demo/woodland-walk.png', $walk->fresh()->featured_image_path);
        $this->assertSame('Original image', $walk->fresh()->featured_image_alt_text);
        $this->assertSame(1, SiteMedia::query()->where('health_status', '!=', 'removed')->count());
        $discarded = SiteMedia::query()->whereKeyNot($original->id)->sole();
        $this->assertSame('removed', $discarded->health_status);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $discarded->id, 'action' => 'removal_requested']);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $discarded->id, 'action' => 'removed']);
        $this->assertSame(4, count(Storage::disk('local')->allFiles('site-media')));
    }

    public function test_two_stale_saves_leave_one_valid_winner_without_orphaning_active_media(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $walk = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $walk,
            $this->input('managed', upload: $this->image('initial.jpg'), alt: 'Initial image'),
        );
        $initial = $walk->featuredMedia;
        $firstStale = $walk->fresh();
        $secondStale = $walk->fresh();

        $firstSave = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $firstStale,
            $this->input('managed', upload: $this->image('first.jpg'), alt: 'First save'),
        );
        $firstWinner = $firstSave->featuredMedia;
        $secondSave = app(UpdateWalkFeaturedImage::class)->handle(
            $owner,
            $secondStale,
            $this->input('managed', upload: $this->image('second.jpg'), alt: 'Second save'),
        );
        $winner = $secondSave->featuredMedia;

        $this->assertNotSame($firstWinner->id, $winner->id);
        $this->assertSame($winner->id, $walk->fresh()->featured_image_media_id);
        $this->assertNull($winner->fresh()->orphaned_at);
        $this->assertNotNull($initial->fresh()->orphaned_at);
        $this->assertNotNull($firstWinner->fresh()->orphaned_at);
    }

    public function test_input_contract_rejects_mixed_sources_and_requires_descriptions_for_active_images(): void
    {
        foreach ([
            ['featured_image_source' => 'managed', 'featured_image_external_url' => 'https://images.example.org/walk.jpg', 'featured_image_alt_text' => 'Mixed'],
            ['featured_image_source' => 'external', 'featured_image_upload' => $this->image('mixed.jpg'), 'featured_image_external_url' => 'https://images.example.org/walk.jpg', 'featured_image_alt_text' => 'Mixed'],
            ['featured_image_source' => 'external', 'featured_image_external_url' => 'http://images.example.org/walk.jpg', 'featured_image_alt_text' => 'Unsafe'],
            ['featured_image_source' => 'managed', 'featured_image_upload' => $this->image('missing-alt.jpg'), 'featured_image_alt_text' => ''],
        ] as $state) {
            try {
                WalkFeaturedImageInput::from($state);
                $this->fail('Invalid featured-image state was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_general_walk_writers_cannot_mutate_featured_image_sources(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, ['featured_image_path' => '/images/demo/woodland-walk.png']);

        app(SaveWalkDraft::class)->update($walk, $owner, [
            'title' => 'Draft title remains editable',
            'featured_image_path' => 'https://attacker.example/ignored.jpg',
            'featured_image_media_id' => 999,
            'featured_image_alt_text' => 'Ignored description',
        ]);

        $this->assertSame('/images/demo/woodland-walk.png', $walk->fresh()->featured_image_path);
        $this->assertNull($walk->fresh()->featured_image_media_id);

        try {
            app(UpdateWalk::class)->handle($walk->fresh(), $owner, [
                'featured_image_path' => 'https://attacker.example/rejected.jpg',
            ]);
            $this->fail('The generic Walk update boundary accepted image-source mutation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('featured_image_path', $exception->errors());
        }

        $this->assertSame('/images/demo/woodland-walk.png', $walk->fresh()->featured_image_path);
    }

    private function input(
        string $source,
        ?UploadedFile $upload = null,
        ?string $external = null,
        ?string $alt = null,
    ): WalkFeaturedImageInput {
        return WalkFeaturedImageInput::from([
            'featured_image_source' => $source,
            'featured_image_upload' => $upload,
            'featured_image_external_url' => $external,
            'featured_image_alt_text' => $alt,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function walk(User $owner, array $attributes = []): Walk
    {
        $event = Event::factory()->for($owner, 'organiser')->create([
            'status' => EventStatus::Draft,
            'is_public' => false,
        ]);

        return Walk::query()->create([
            'event_id' => $event->id,
            'primary_leader_id' => $owner->id,
            ...$attributes,
        ])->load('event');
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 80, 60);
    }
}
