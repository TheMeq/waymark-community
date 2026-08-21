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

    private function photoFor(Event $event): CommunityPhoto
    {
        return CommunityPhoto::query()->create([
            'event_id' => $event->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'processing_status' => 'complete',
            'storage_disk' => 'local',
            'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/master.jpg',
            'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/master.jpg'],
            'moderation_status' => 'pending',
            'caption' => 'Queue photo '.$event->id,
        ]);
    }
}
