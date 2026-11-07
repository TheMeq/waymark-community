<?php

namespace Tests\Feature\Holidays;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\HolidayResource\Pages\ListHolidays;
use App\Models\User;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicHolidayDetailViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class HolidaySoldOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_a_holiday_sold_out_without_changing_publication_or_other_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $holiday = app(CreateHoliday::class)->handle($admin, [
            'title' => 'Coastal break', 'starts_at' => '2027-06-01 10:00:00',
            'ends_at' => '2027-06-05 16:00:00', 'destination' => 'Coast', 'availability' => 'Places available',
        ]);
        $holiday->event->update(['status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()]);

        Livewire::actingAs($admin)->test(ListHolidays::class)
            ->callTableAction('soldOut', $holiday)->assertHasNoTableActionErrors()
            ->assertNotified('Holiday marked sold out')
            ->assertTableActionHidden('soldOut', $holiday);

        $holiday->refresh();
        $this->assertSame('Sold out', $holiday->availability);
        $this->assertSame('Coast', $holiday->destination);
        $this->assertSame(EventStatus::Published, $holiday->event->status);
        $this->assertTrue($holiday->event->is_public);
        $this->assertSame('Sold out', PublicHolidayCardViewModel::fromEvent($holiday->event)['status']);
        $this->assertSame('Sold out', PublicHolidayDetailViewModel::fromEvent($holiday->event)['availability']);

        Livewire::actingAs($admin)->test(EditHoliday::class, ['record' => $holiday->getRouteKey()])
            ->fillForm(['availability' => 'Places available'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Places available', $holiday->fresh()->availability);
    }
}
