<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Filament\Pages\PhotoModeration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
