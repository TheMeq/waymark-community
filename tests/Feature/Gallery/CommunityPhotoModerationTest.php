<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Data\CommunityPhotoModerationRequest;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoModerationAudit;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CommunityPhotoModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_leader_can_moderate_only_photos_for_their_own_event(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $ownPhoto = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $otherPhoto = $this->photoFor(Event::factory()->create());

        $this->assertTrue($leader->hasCapability(ModuleCapability::ModerateOwnEventPhotos));
        $this->assertFalse($leader->hasCapability(ModuleCapability::ModerateAllCommunityPhotos));
        $this->assertSame([$ownPhoto->id], app(ModeratableCommunityPhotos::class)->for($leader)->pluck('id')->all());

        app(ModerateCommunityPhoto::class)->approve($leader, $ownPhoto);

        $this->assertSame('approved', $ownPhoto->fresh()->moderation_status);

        $this->expectException(AuthorizationException::class);
        app(ModerateCommunityPhoto::class)->approve($leader, $otherPhoto);
    }

    public function test_global_moderator_can_moderate_event_and_special_album_photos(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $eventPhoto = $this->photoFor(Event::factory()->create());
        $album = SpecialAlbum::query()->create(['title' => 'Committee archive', 'slug' => 'committee-archive']);
        $albumPhoto = $this->photoFor($album);

        $this->assertTrue($moderator->hasCapability(ModuleCapability::ModerateAllCommunityPhotos));
        $ids = app(ModeratableCommunityPhotos::class)->for($moderator)->pluck('id')->sort()->values()->all();
        $this->assertSame([$eventPhoto->id, $albumPhoto->id], $ids);

        app(ModerateCommunityPhoto::class)->approve($moderator, $albumPhoto);
        $this->assertSame('approved', $albumPhoto->fresh()->moderation_status);
    }

    public function test_mutations_are_audited_atomically_and_featured_is_unique_per_context(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $event = Event::factory()->create();
        $first = $this->photoFor($event);
        $second = $this->photoFor($event);
        $action = app(ModerateCommunityPhoto::class);

        $action->approve($moderator, $first);
        $action->approve($moderator, $second);
        $action->feature($moderator, $first);
        $action->feature($moderator, $second);
        $action->edit($moderator, $second, new CommunityPhotoModerationRequest(caption: 'Sunset ridge', photographerName: 'A. Walker'));

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
        $this->assertSame('Sunset ridge', $second->fresh()->caption);
        $this->assertSame(5, CommunityPhotoModerationAudit::query()->count());
        $this->assertSame('edited', CommunityPhotoModerationAudit::query()->latest('id')->value('action'));
    }

    public function test_incomplete_photo_cannot_be_approved_or_featured_and_leaves_no_audit(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $photo = $this->photoFor(Event::factory()->create(), ['processing_status' => 'failed', 'processed_variants' => null]);

        try {
            app(ModerateCommunityPhoto::class)->approve($moderator, $photo);
            $this->fail('An incomplete photo was approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('photo', $exception->errors());
        }

        $this->assertSame('pending', $photo->fresh()->moderation_status);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_move_preserves_exactly_one_context_and_organiser_scope(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $ownEvent = Event::factory()->for($leader, 'organiser')->create();
        $otherEvent = Event::factory()->create();
        $photo = $this->photoFor($ownEvent);

        $this->expectException(AuthorizationException::class);
        app(ModerateCommunityPhoto::class)->move($leader, $photo, 'event:'.$otherEvent->id);
    }

    public function test_bulk_mutation_rejects_mixed_selection_without_changing_authorised_photo(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $other = $this->photoFor(Event::factory()->create());

        try {
            app(ModerateCommunityPhoto::class)->bulkApprove($leader, [$own->id, $other->id]);
            $this->fail('A mixed bulk selection was accepted.');
        } catch (AuthorizationException) {
            // Expected: authorization happens before any record is mutated.
        }

        $this->assertSame('pending', $own->fresh()->moderation_status);
        $this->assertSame('pending', $other->fresh()->moderation_status);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_rotate_and_remove_keep_media_retained_and_audited(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $photo = $this->photoFor(Event::factory()->create());
        $action = app(ModerateCommunityPhoto::class);

        $action->approve($moderator, $photo);
        $action->rotate($moderator, $photo, 90);
        $action->remove($moderator, $photo);

        $fresh = $photo->fresh();
        $this->assertSame(90, $fresh->presentation_rotation);
        $this->assertSame('removed', $fresh->moderation_status);
        $this->assertNotNull($fresh->source_path);
        $this->assertNotEmpty($fresh->processed_variants);
        $this->assertSame(['approved', 'rotated', 'removed'], CommunityPhotoModerationAudit::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_audit_failure_rolls_back_the_moderation_mutation(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $photo = $this->photoFor(Event::factory()->create());

        CommunityPhotoModerationAudit::creating(static function (): never {
            throw new \RuntimeException('Audit storage is unavailable.');
        });

        try {
            app(ModerateCommunityPhoto::class)->approve($moderator, $photo);
            $this->fail('Approval was committed without its audit record.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit storage is unavailable.', $exception->getMessage());
        } finally {
            CommunityPhotoModerationAudit::flushEventListeners();
        }

        $this->assertSame('pending', $photo->fresh()->moderation_status);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    private function photoFor(Event|SpecialAlbum $context, array $overrides = []): CommunityPhoto
    {
        return CommunityPhoto::query()->create(array_merge([
            'event_id' => $context instanceof Event ? $context->id : null,
            'special_album_id' => $context instanceof SpecialAlbum ? $context->id : null,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'processing_status' => 'complete',
            'storage_disk' => 'local',
            'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/master.jpg',
            'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/master.jpg'],
            'moderation_status' => 'pending',
        ], $overrides));
    }
}
