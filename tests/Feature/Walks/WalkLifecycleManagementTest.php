<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Queries\PublicEventsQuery;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Queries\UploadablePublicEvents;
use App\Domain\SiteMedia\Actions\MarkSiteMediaOrphaned;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use App\Domain\Walks\Actions\DeleteWalk;
use App\Domain\Walks\Actions\UnpublishWalk;
use App\Domain\Walks\Actions\UpdateWalkFeaturedImage;
use App\Domain\Walks\Data\WalkFeaturedImageInput;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Filament\Resources\WalkResource\Pages\ListWalks;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkLifecycleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublish_returns_walk_to_draft_and_removes_it_from_public_surfaces(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        Livewire::actingAs($owner)->test(EditWalk::class, ['record' => $walk->id])
            ->assertActionVisible('unpublish')
            ->callAction('unpublish')->assertHasNoActionErrors()
            ->assertNotified('Walk unpublished')
            ->assertSee('Status: Draft')
            ->assertActionHidden('unpublish')
            ->assertActionVisible('submitForPublication');
        $event = $walk->event->fresh();
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertFalse($event->is_public);
        $this->assertNull($event->published_at);
        $this->get('/walks/'.$event->slug)->assertNotFound();
        $this->get('/walks')->assertDontSee($event->title);
        $this->get('/')->assertDontSee($event->title);
        $this->assertFalse(app(PublicEventsQuery::class)->published()->whereKey($event->id)->exists());
        $this->assertNull(app(UploadablePublicEvents::class)->find($event->id));
        $this->assertDatabaseHas('walks', ['id' => $walk->id, 'private_organiser_notes' => 'Keep these notes.']);
    }

    public function test_delete_from_edit_page_removes_walk_but_retains_its_event_and_photos(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $photo = CommunityPhoto::query()->create([
            'event_id' => $walk->event_id, 'uploader_id' => $owner->id,
            'media_type' => 'image', 'processing_status' => 'pending',
            'storage_disk' => 'local', 'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/source.jpg',
            'processed_variants' => [], 'moderation_status' => 'pending',
        ]);
        Livewire::actingAs($owner)->test(EditWalk::class, ['record' => $walk->id])
            ->assertActionVisible('delete')->callAction('delete')
            ->assertHasNoActionErrors()->assertNotified('Walk deleted')
            ->assertRedirect(WalkResource::getUrl('index'));
        $this->assertSoftDeleted($walk);
        $event = $walk->event->fresh();
        $this->assertSame(EventStatus::Archived, $event->status);
        $this->assertFalse($event->is_public);
        $this->assertNull($event->published_at);
        $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'event_id' => $event->id]);
        Livewire::test(ListWalks::class)->assertCanNotSeeTableRecords([$walk]);
        $this->get('/walks/'.$event->slug)->assertNotFound();
    }

    public function test_walk_list_can_delete_a_draft_without_opening_the_wizard(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner, EventStatus::Draft);
        Livewire::actingAs($owner)->test(ListWalks::class)
            ->assertTableActionVisible('delete', $walk)
            ->callTableAction('delete', $walk)->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$walk]);
        $this->assertSoftDeleted($walk);
    }

    public function test_another_leader_cannot_unpublish_someone_elses_walk(): void
    {
        $walk = $this->walk(User::factory()->walkLeader()->create());
        $this->expectException(AuthorizationException::class);
        app(UnpublishWalk::class)->handle($walk, User::factory()->walkLeader()->create());
    }

    public function test_another_leader_cannot_delete_someone_elses_walk(): void
    {
        $walk = $this->walk(User::factory()->walkLeader()->create());
        $this->expectException(AuthorizationException::class);
        app(DeleteWalk::class)->handle($walk, User::factory()->walkLeader()->create());
    }

    public function test_administrator_can_unpublish_and_delete_another_leaders_walk(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $walk = $this->walk(User::factory()->walkLeader()->create());
        app(UnpublishWalk::class)->handle($walk, $administrator);
        app(DeleteWalk::class)->handle($walk, $administrator);
        $this->assertSoftDeleted($walk);
    }

    public function test_deleting_walk_keeps_its_managed_image_protected_from_orphan_cleanup(): void
    {
        Storage::fake('local');
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $walk = app(UpdateWalkFeaturedImage::class)->handle($owner, $walk,
            WalkFeaturedImageInput::from([
                'featured_image_source' => 'managed',
                'featured_image_upload' => UploadedFile::fake()->image('walk.jpg', 1200, 600),
                'featured_image_alt_text' => 'Walkers on a ridge',
            ]));
        $media = $walk->featuredMedia;
        app(DeleteWalk::class)->handle($walk, $owner);
        $this->assertTrue(app(SiteMediaUsage::class)->isUsed($media));
        $this->assertFalse(app(MarkSiteMediaOrphaned::class)->handle($media));
        $this->assertNull($media->fresh()->orphaned_at);
        Storage::disk($media->storage_disk)->assertExists($media->processed_variants['large']);
    }

    public function test_failed_delete_rolls_back_the_events_publication_state(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $failDelete = true;
        Walk::deleting(function () use (&$failDelete): void {
            if ($failDelete) {
                throw new \RuntimeException('Simulated delete failure');
            }
        });
        try {
            app(DeleteWalk::class)->handle($walk, $owner);
            $this->fail('Expected deletion to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated delete failure', $exception->getMessage());
        } finally {
            $failDelete = false;
        }
        $this->assertNotSoftDeleted($walk);
        $event = $walk->event->fresh();
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertTrue($event->is_public);
        $this->assertNotNull($event->published_at);
    }

    public function test_cancelled_delete_does_not_archive_the_walk(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $walk = $this->walk($owner);
        $cancelDelete = true;
        Walk::deleting(function () use (&$cancelDelete): ?bool {
            return $cancelDelete ? false : null;
        });
        try {
            app(DeleteWalk::class)->handle($walk, $owner);
            $this->fail('Expected deletion to be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('walk', $exception->errors());
        } finally {
            $cancelDelete = false;
        }
        $this->assertNotSoftDeleted($walk);
        $this->assertTrue($walk->event->fresh()->is_public);
        $this->assertSame(EventStatus::Published, $walk->event->fresh()->status);
    }

    private function walk(User $owner, EventStatus $status = EventStatus::Published): Walk
    {
        return Walk::query()->create([
            'event_id' => Event::factory()->for($owner, 'organiser')->create([
                'title' => 'Lifecycle test walk', 'slug' => 'lifecycle-test-walk',
                'starts_at' => now()->addDay(), 'status' => $status,
                'is_public' => $status === EventStatus::Published,
                'published_at' => $status === EventStatus::Published ? now()->subMinute() : null,
            ])->id,
            'primary_leader_id' => $owner->id, 'private_organiser_notes' => 'Keep these notes.',
        ]);
    }
}
