<?php

namespace Tests\Feature\Events;

use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\Socials\Actions\CreateSocial;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\SocialResource\Pages\EditSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->fillForm(['organiser_id' => null])->call('save')->assertHasFormErrors(['organiser_id' => 'required']);
        $this->assertSame($organiser->id, $record->event->fresh()->organiser_id);

        Livewire::actingAs(User::factory()->create())->test($page, ['record' => $record->getRouteKey()])
            ->assertForbidden();
    }
}
