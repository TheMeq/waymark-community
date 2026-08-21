<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Filament\Pages\PhotoModeration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class PhotoModerationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_bespoke_page_shows_only_an_organisers_pending_event_queue_and_can_approve_a_photo(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $other = $this->photoFor(Event::factory()->create());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($leader)
            ->get('/admin/photo-moderation')
            ->assertSuccessful()
            ->assertSeeText('Photo moderation')
            ->assertSeeText($own->caption)
            ->assertDontSeeText($other->caption);

        Livewire::actingAs($leader)
            ->test(PhotoModeration::class)
            ->call('approve', $own->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $own->fresh()->moderation_status);
    }

    public function test_private_preview_requires_moderation_scope_and_never_streams_a_persisted_unsafe_reference(): void
    {
        Storage::fake('local');
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $other = $this->photoFor(Event::factory()->create());
        Storage::disk('local')->put($own->processed_variants['master'], 'safe private image');

        $response = $this->actingAs($leader)
            ->get(route('admin.photo-moderation.preview', $own))
            ->assertSuccessful();
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->actingAs($leader)
            ->get(route('admin.photo-moderation.preview', $other))
            ->assertForbidden();

        DB::table('community_photos')->where('id', $own->id)->update([
            'processed_variants' => json_encode(['master' => '../.env'], JSON_THROW_ON_ERROR),
        ]);
        $this->assertSame('../.env', CommunityPhoto::query()->findOrFail($own->id)->processed_variants['master']);

        $this->actingAs($leader)
            ->get(route('admin.photo-moderation.preview', $own))
            ->assertNotFound();
    }

    public function test_livewire_rejects_forged_out_of_scope_ids_for_each_mutation_without_audit_or_photo_changes(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $other = $this->photoFor(Event::factory()->create());
        $original = $other->fresh()->only(['caption', 'event_id', 'moderation_status', 'presentation_rotation', 'is_featured']);

        foreach ([
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->call('edit', $other->id, 'Forged', 'Forged'),
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->call('move', $other->id, 'event:'.$own->event_id),
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->call('rotate', $other->id, 90),
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->call('remove', $other->id),
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->call('feature', $other->id),
            fn () => Livewire::actingAs($leader)->test(PhotoModeration::class)->set('selectedPhotoIds', [$own->id, $other->id])->call('bulkApprove'),
        ] as $attempt) {
            $attempt();
        }

        $this->assertSame($original, $other->fresh()->only(array_keys($original)));
        $this->assertSame('pending', $own->fresh()->moderation_status);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_livewire_edit_and_move_is_atomic_when_the_destination_is_out_of_scope(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->photoFor(Event::factory()->for($leader, 'organiser')->create());
        $otherEvent = Event::factory()->create();

        Livewire::actingAs($leader)
            ->test(PhotoModeration::class)
            ->set('editingPhotoId', $own->id)
            ->set('caption', 'This must not persist')
            ->set('photographerName', 'Wrong destination')
            ->set('targetContext', 'event:'.$otherEvent->id)
            ->call('saveEditing');

        $fresh = $own->fresh();
        $this->assertSame($own->caption, $fresh->caption);
        $this->assertSame($own->photographer_name, $fresh->photographer_name);
        $this->assertSame($own->event_id, $fresh->event_id);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);
    }

    public function test_unprocessed_and_missing_preview_rows_explain_state_without_broken_images_or_actions(): void
    {
        Storage::fake('local');
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->for($leader, 'organiser')->create();
        foreach (['queued', 'processing', 'retry', 'terminal_failed'] as $status) {
            $this->photoFor($event)->update(['processing_status' => $status, 'processed_variants' => null]);
        }
        $missing = $this->photoFor($event);
        $missing->update(['source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/missing.jpg', 'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/missing.jpg']]);
        $complete = $this->photoFor($event);
        Storage::disk('local')->put($complete->processed_variants['master'], 'preview');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $response = $this->actingAs($leader)->get('/admin/photo-moderation')->assertOk();
        $response->assertSeeText('Processing queued')->assertSeeText('Processing in progress')
            ->assertSeeText('Processing retrying')->assertSeeText('Processing failed')->assertSeeText('Preview unavailable');
        $response->assertDontSee(route('admin.photo-moderation.preview', $missing), false);
        $response->assertSee(route('admin.photo-moderation.preview', $complete), false);
        $this->assertSame(1, substr_count($response->getContent(), 'wire:click="approve('));
        $this->assertSame(1, substr_count($response->getContent(), 'wire:click="rotate('));
        $this->assertSame(1, substr_count($response->getContent(), 'wire:model.live="selectedPhotoIds"'));
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'disabled'));
    }

    private function photoFor(Event $event): CommunityPhoto
    {
        $path = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c'.str_pad((string) (CommunityPhoto::query()->count() + 1), 4, '0', STR_PAD_LEFT).'/master.jpg';
        Storage::disk('local')->put($path, 'preview');

        return CommunityPhoto::query()->create([
            'event_id' => $event->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'processing_status' => 'complete',
            'storage_disk' => 'local',
            'source_path' => $path,
            'processed_variants' => ['master' => $path],
            'moderation_status' => 'pending',
            'caption' => 'Queue photo '.$event->id,
        ]);
    }
}
