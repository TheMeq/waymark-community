<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\Socials\Actions\CreateSocial;
use App\Filament\Resources\HolidayResource\Pages\CreateHoliday as CreateHolidayPage;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Filament\Resources\SocialResource\Pages\CreateSocial as CreateSocialPage;
use App\Filament\Resources\SocialResource\Pages\EditSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HolidaySocialCreationSlugTest extends TestCase
{
    use RefreshDatabase;

    public static function eventKinds(): array
    {
        return [
            'holiday' => [CreateHoliday::class, CreateHolidayPage::class, EditHoliday::class, 'holiday'],
            'social' => [CreateSocial::class, CreateSocialPage::class, EditSocial::class, 'social'],
        ];
    }

    #[DataProvider('eventKinds')]
    public function test_creation_generates_a_slug_without_manual_input(string $action, string $page, string $editPage, string $fallback): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Livewire::actingAs($administrator)->test($page)
            ->assertFormFieldHidden('slug')
            ->fillForm($this->attributes())->call('create')->assertHasNoFormErrors();
        $event = Event::query()->where('slug', 'coast-and-country-2026-11-06')->sole();
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertFalse($event->is_public);
    }

    #[DataProvider('eventKinds')]
    public function test_generated_slug_avoids_collisions_with_every_event_type(string $action, string $page, string $editPage, string $fallback): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        Event::factory()->create(['slug' => 'coast-and-country-2026-11-06']);
        $first = app($action)->handle($administrator, $this->attributes());
        $second = app($action)->handle($administrator, $this->attributes());
        $this->assertSame('coast-and-country-2026-11-06-2', $first->event->slug);
        $this->assertSame('coast-and-country-2026-11-06-3', $second->event->slug);
    }

    #[DataProvider('eventKinds')]
    public function test_blank_slug_uses_the_event_type_when_title_has_no_slug_characters(string $action, string $page, string $editPage, string $fallback): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $event = app($action)->handle($administrator, [...$this->attributes(), 'title' => '!!!', 'slug' => '   ']);
        $this->assertSame($fallback.'-2026-11-06', $event->event->slug);
    }

    #[DataProvider('eventKinds')]
    public function test_existing_explicit_slugs_remain_supported_and_title_edits_preserve_them(string $action, string $page, string $editPage, string $fallback): void
    {
        $administrator = User::factory()->initialAdministrator()->create();
        $record = app($action)->handle($administrator, [...$this->attributes(), 'slug' => 'existing-address']);
        Livewire::actingAs($administrator)->test($editPage, ['record' => $record->id])
            ->assertFormFieldVisible('slug')
            ->fillForm(['title' => 'Updated event title', 'starts_at' => '2026-11-07 09:00:00'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('existing-address', $record->event->fresh()->slug);
        $this->assertSame('Updated event title', $record->event->fresh()->title);
    }

    private function attributes(): array
    {
        return [
            'title' => 'Coast and Country', 'starts_at' => '2026-11-06 16:00:00',
            'ends_at' => '2026-11-09 10:00:00',
        ];
    }
}
