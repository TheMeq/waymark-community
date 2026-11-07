<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\Socials\Actions\CreateSocial;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\SocialResource\Pages\EditSocial;
use App\Models\User;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicHolidayDetailViewModel;
use App\ViewModels\PublicSocialCardViewModel;
use App\ViewModels\PublicSocialDetailViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EventOrganiserEditingTest extends TestCase
{
    use RefreshDatabase;

    public static function types(): array
    {
        return [[CreateHoliday::class, EditHoliday::class], [CreateSocial::class, EditSocial::class]];
    }

    #[DataProvider('types')]
    public function test_admin_can_change_the_organiser_and_the_selection_persists(string $create, string $page): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organiser = User::factory()->create();
        $record = app($create)->handle($admin, ['title' => 'Group trip', 'starts_at' => '2027-06-01 10:00:00', 'ends_at' => '2027-06-03 16:00:00']);

        Livewire::actingAs($admin)->test($page, ['record' => $record->getRouteKey()])
            ->assertFormSet(['organiser_id' => $admin->id])
            ->fillForm(['organiser_id' => $organiser->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($organiser->id, $record->event->fresh()->organiser_id);
        Livewire::actingAs($admin)->test($page, ['record' => $record->getRouteKey()])
            ->assertFormSet(['organiser_id' => $organiser->id]);

        Livewire::actingAs($admin)->test($page, ['record' => $record->getRouteKey()])
            ->fillForm(['organiser_id' => null, 'organiser_name' => 'Pat Smith'])->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->event->fresh()->organiser_id);
        $this->assertSame('Pat Smith', $record->event->fresh()->organiser_name);
        $this->assertSame('Pat Smith', $record->event->fresh()->organiserDisplayName());

        Livewire::actingAs($admin)->test($page, ['record' => $record->getRouteKey()])
            ->assertFormSet(['organiser_name' => 'Pat Smith', 'organiser_id' => null])
            ->fillForm(['organiser_id' => $organiser->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($organiser->publicDisplayName(), $record->event->fresh()->organiserDisplayName());
        $this->assertSame('Pat Smith', $record->event->fresh()->organiser_name);

        Livewire::actingAs(User::factory()->create())->test($page, ['record' => $record->getRouteKey()])
            ->assertForbidden();
    }

    #[DataProvider('types')]
    public function test_name_only_organisers_can_be_created_published_and_shown_publicly(string $create, string $page): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = app($create)->handle($admin, [
            'title' => 'Group trip', 'starts_at' => '2027-06-01 10:00:00', 'ends_at' => '2027-06-03 16:00:00',
            'organiser_name' => '  Pat Smith  ', 'organiser_id' => null,
        ]);
        app(PublishEvent::class)->handle($record->event, $admin);
        $event = $record->event->fresh();
        $this->assertNull($event->organiser_id);
        $this->assertSame('Pat Smith', $event->organiser_name);
        $card = $create === CreateHoliday::class ? PublicHolidayCardViewModel::class : PublicSocialCardViewModel::class;
        $detail = $create === CreateHoliday::class ? PublicHolidayDetailViewModel::class : PublicSocialDetailViewModel::class;
        $this->assertSame('Pat Smith', $card::fromEvent($event)['leader']);
        $this->assertSame('Pat Smith', $detail::fromEvent($event)['organiser']);
    }

    #[DataProvider('types')]
    public function test_unknown_linked_accounts_are_rejected(string $create, string $page): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->expectException(ValidationException::class);
        app($create)->handle($admin, [
            'title' => 'Group trip', 'starts_at' => '2027-06-01 10:00:00', 'ends_at' => '2027-06-03 16:00:00',
            'organiser_name' => 'Pat Smith', 'organiser_id' => 999999,
        ]);
    }
}
