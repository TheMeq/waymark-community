<?php

namespace Tests\Feature\Events;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\Socials\Actions\CreateSocial;
use App\Filament\Resources\HolidayResource;
use App\Filament\Resources\HolidayResource\Pages\ListHolidays;
use App\Filament\Resources\SocialResource;
use App\Filament\Resources\SocialResource\Pages\ListSocials;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EventListActionsTest extends TestCase
{
    use RefreshDatabase;

    public static function types(): array
    {
        return [
            'holidays' => ['holiday', ListHolidays::class, HolidayResource::class, 'Holiday published'],
            'events' => ['social', ListSocials::class, SocialResource::class, 'Social published'],
        ];
    }

    #[DataProvider('types')]
    public function test_drafts_offer_continue_and_publish_while_published_records_offer_edit(string $type, string $page, string $resource, string $notice): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $draft = $this->draft($admin, $type, 'Draft record', '2026-12-01');
        $published = $this->draft($admin, $type, 'Published record', '2026-12-08');
        $published->event->update(['status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()]);
        Livewire::actingAs($admin)->test($page)
            ->assertTableActionHasLabel('edit', 'Continue draft', $draft)
            ->assertTableActionHasUrl('edit', $resource::getUrl('edit', ['record' => $draft]), $draft)
            ->assertTableActionVisible('publish', $draft)
            ->assertTableActionHasLabel('publish', 'Publish', $draft)
            ->assertTableActionHasLabel('edit', 'Edit', $published)
            ->assertTableActionHidden('publish', $published);
    }

    #[DataProvider('types')]
    public function test_row_publication_uses_the_existing_event_workflow(string $type, string $page, string $resource, string $notice): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $draft = $this->draft($admin, $type, 'Ready record', '2026-12-01');
        Livewire::actingAs($admin)->test($page)
            ->callTableAction('publish', $draft)->assertHasNoTableActionErrors()
            ->assertNotified($notice)
            ->assertTableActionHidden('publish', $draft)
            ->assertTableActionHasLabel('edit', 'Edit', $draft);
        $this->assertSame(EventStatus::Published, $draft->event->fresh()->status);
        $this->assertTrue($draft->event->fresh()->is_public);
        $this->assertNotNull($draft->event->fresh()->published_at);
    }

    #[DataProvider('types')]
    public function test_lists_default_to_latest_start_and_still_support_ascending_order(string $type, string $page, string $resource, string $notice): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $latest = $this->draft($admin, $type, 'Latest', '2027-01-01');
        $earliest = $this->draft($admin, $type, 'Earliest', '2025-01-01');
        $middle = $this->draft($admin, $type, 'Middle', '2026-01-01');
        Livewire::actingAs($admin)->test($page)
            ->assertCanSeeTableRecords([$latest, $middle, $earliest], inOrder: true)
            ->sortTable('event.starts_at', 'asc')
            ->assertCanSeeTableRecords([$earliest, $middle, $latest], inOrder: true);
    }

    #[DataProvider('types')]
    public function test_accounts_without_the_module_permission_cannot_access_the_list(string $type, string $page, string $resource, string $notice): void
    {
        Livewire::actingAs(User::factory()->walkLeader()->create())->test($page)->assertForbidden();
    }

    private function draft(User $actor, string $type, string $title, string $date): Model
    {
        $attributes = ['title' => $title, 'starts_at' => $date.' 10:00:00', 'ends_at' => $date.' 16:00:00'];

        return $type === 'holiday'
            ? app(CreateHoliday::class)->handle($actor, $attributes)
            : app(CreateSocial::class)->handle($actor, $attributes);
    }
}
