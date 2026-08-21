<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\SaveFavourite;
use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FavouritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_favourites_are_event_scoped_with_unique_user_event_records(): void
    {
        $this->assertTrue(Schema::hasTable('favourites'));
        $this->assertTrue(Schema::hasColumns('favourites', ['user_id', 'event_id', 'created_at', 'updated_at']));

        $user = User::factory()->create();
        $event = $this->publicEvent(EventType::Walk, 'Unique favourite');

        Favourite::query()->create(['user_id' => $user->id, 'event_id' => $event->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        Favourite::query()->create(['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_logged_in_users_can_save_each_approved_public_top_level_type_idempotently(): void
    {
        $user = User::factory()->unverified()->create();
        $walk = $this->publicEvent(EventType::Walk, 'Saved walk');
        $social = $this->publicEvent(EventType::Social, 'Saved social');
        $holiday = $this->publicEvent(EventType::Holiday, 'Saved holiday');

        foreach ([$walk, $social, $holiday] as $event) {
            $this->actingAs($user)->post(route('favourites.store', $event))->assertRedirect();
            $this->actingAs($user)->post(route('favourites.store', $event))->assertRedirect();
        }

        $this->assertSame(3, Favourite::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('favourites', 3);
    }

    public function test_save_favourite_recovers_only_a_unique_pair_race(): void
    {
        $user = User::factory()->create();
        $event = $this->publicEvent(EventType::Walk, 'Raced favourite');
        $existing = Favourite::query()->create(['user_id' => $user->id, 'event_id' => $event->id]);
        $action = new SaveFavourite(
            app(FavouriteablePublicEventsQuery::class),
            fn () => throw new UniqueConstraintViolationException('sqlite', 'insert into favourites', [], new \RuntimeException('unique pair')),
        );
        $favourite = $action->handle($user, $event);

        $this->assertSame($existing->id, $favourite->id);
        $this->assertDatabaseCount('favourites', 1);
    }

    public function test_save_favourite_does_not_mask_non_unique_database_errors(): void
    {
        $user = User::factory()->create();
        $event = $this->publicEvent(EventType::Walk, 'Failed favourite');

        $action = new SaveFavourite(
            app(FavouriteablePublicEventsQuery::class),
            fn () => throw new QueryException('sqlite', 'insert into favourites', [], new \RuntimeException('simulated failure')),
        );

        $this->expectException(QueryException::class);
        $action->handle($user, $event);
    }

    public function test_favourites_reject_private_future_published_unsupported_and_holiday_child_events(): void
    {
        $user = User::factory()->create();
        $private = $this->publicEvent(EventType::Walk, 'Private favourite', ['is_public' => false]);
        $draft = $this->publicEvent(EventType::Walk, 'Draft favourite', ['status' => EventStatus::Draft]);
        $scheduled = $this->publicEvent(EventType::Social, 'Scheduled favourite', ['published_at' => now()->addMinute()]);
        $completedHoliday = $this->publicEvent(EventType::Holiday, 'Completed holiday favourite', ['status' => EventStatus::Completed]);
        $unsupported = Event::factory()->create([
            'type' => EventType::Walk,
            'title' => 'Unsupported favourite',
            'slug' => 'unsupported-favourite',
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ]);
        $holiday = $this->publicEvent(EventType::Holiday, 'Parent holiday');
        $child = $this->publicEvent(EventType::Social, 'Holiday child', ['parent_event_id' => $holiday->id]);

        foreach ([$private, $draft, $scheduled, $completedHoliday, $unsupported, $child] as $event) {
            $this->actingAs($user)->post(route('favourites.store', $event))->assertNotFound();
        }

        $this->assertDatabaseCount('favourites', 0);
    }

    public function test_favourites_are_private_and_hide_items_that_are_no_longer_public(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $saved = $this->publicEvent(EventType::Walk, 'Only my saved walk');
        $stale = $this->publicEvent(EventType::Holiday, 'Withdrawn weekend');
        $otherSaved = $this->publicEvent(EventType::Social, 'Other persons social');

        Favourite::query()->create(['user_id' => $owner->id, 'event_id' => $saved->id]);
        Favourite::query()->create(['user_id' => $owner->id, 'event_id' => $stale->id]);
        Favourite::query()->create(['user_id' => $other->id, 'event_id' => $otherSaved->id]);
        $stale->update(['is_public' => false]);

        $this->actingAs($owner)->get(route('account.favourites.index'))
            ->assertOk()
            ->assertSee('Only my saved walk')
            ->assertDontSee('Withdrawn weekend')
            ->assertDontSee('Other persons social')
            ->assertDontSee('favourite-count', false)
            ->assertDontSee($owner->name)
            ->assertDontSee($owner->email);
    }

    public function test_one_user_cannot_remove_another_users_favourite_and_can_remove_their_own(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->publicEvent(EventType::Walk, 'Protected saved walk');
        Favourite::query()->create(['user_id' => $owner->id, 'event_id' => $event->id]);

        $this->actingAs($other)->delete(route('favourites.destroy', $event))->assertNotFound();
        $this->assertDatabaseHas('favourites', ['user_id' => $owner->id, 'event_id' => $event->id]);

        $this->actingAs($owner)->delete(route('favourites.destroy', $event))->assertRedirect();
        $this->assertDatabaseMissing('favourites', ['user_id' => $owner->id, 'event_id' => $event->id]);
    }

    public function test_favourites_are_removed_when_their_account_or_event_is_deleted(): void
    {
        $user = User::factory()->create();
        $event = $this->publicEvent(EventType::Social, 'Cascade saved social');
        Favourite::query()->create(['user_id' => $user->id, 'event_id' => $event->id]);

        $event->delete();
        $this->assertDatabaseCount('favourites', 0);

        $event = $this->publicEvent(EventType::Holiday, 'Cascade saved holiday');
        Favourite::query()->create(['user_id' => $user->id, 'event_id' => $event->id]);
        $user->delete();

        $this->assertDatabaseCount('favourites', 0);
    }

    public function test_guests_cannot_mutate_favourites_and_detail_pages_do_not_disclose_favourite_owners_or_counts(): void
    {
        $event = $this->publicEvent(EventType::Walk, 'Guest favourite');

        $this->post(route('favourites.store', $event))->assertRedirect(route('login'));
        $this->delete(route('favourites.destroy', $event))->assertRedirect(route('login'));
        $this->get(route('account.favourites.index'))->assertRedirect(route('login'));
        $this->get(route('walks.show', $event->slug))
            ->assertOk()
            ->assertSee('Sign in to save')
            ->assertDontSee('favourite-count', false)
            ->assertDontSee('saved by', false);
    }

    public function test_approved_public_detail_pages_present_save_state_without_public_favourite_data(): void
    {
        $user = User::factory()->create();
        $walk = $this->publicEvent(EventType::Walk, 'Detail saved walk');
        $social = $this->publicEvent(EventType::Social, 'Detail saved social');
        $holiday = $this->publicEvent(EventType::Holiday, 'Detail saved holiday');
        Favourite::query()->create(['user_id' => $user->id, 'event_id' => $social->id]);

        $this->actingAs($user)->get(route('walks.show', $walk->slug))
            ->assertOk()->assertSee('Save to favourites')->assertDontSee('favourite-count', false);
        $this->actingAs($user)->get(route('socials.show', $social->slug))
            ->assertOk()->assertSee('Remove from favourites')->assertDontSee('favourite-count', false);
        $this->actingAs($user)->get(route('holidays.show', $holiday->slug))
            ->assertOk()->assertSee('Save to favourites')->assertDontSee('favourite-count', false);
    }

    public function test_public_holiday_child_social_has_no_favourite_control_while_a_top_level_social_does(): void
    {
        $user = User::factory()->create();
        $holiday = $this->publicEvent(EventType::Holiday, 'Detail parent holiday');
        $childSocial = $this->publicEvent(EventType::Social, 'Detail holiday child social', ['parent_event_id' => $holiday->id]);
        $topLevelSocial = $this->publicEvent(EventType::Social, 'Detail top-level social');

        $this->actingAs($user)->get(route('socials.show', $childSocial->slug))
            ->assertOk()
            ->assertDontSee('Save to favourites')
            ->assertDontSee('Remove from favourites')
            ->assertDontSee('<form', false);
        $this->actingAs($user)->get(route('socials.show', $topLevelSocial->slug))
            ->assertOk()
            ->assertSee('Save to favourites');
    }

    public function test_favourite_surface_introduces_no_reminder_routes_or_preferences(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => (string) $route->getName())
            ->filter()
            ->implode(' ');
        $migrationContents = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path): string => (string) file_get_contents($path))
            ->implode("\n");

        $this->assertStringNotContainsString('reminder', strtolower($routes));
        $this->assertStringNotContainsString('reminder', strtolower($migrationContents));
        $this->assertStringNotContainsString('notification', strtolower($migrationContents));
    }

    /** @param array<string, mixed> $attributes */
    private function publicEvent(EventType $type, string $title, array $attributes = []): Event
    {
        $startsAt = now()->addWeek();
        $event = Event::factory()->create(array_replace([
            'type' => $type,
            'title' => $title,
            'slug' => str($title)->slug()->toString().'-'.str()->random(6),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));

        match ($type) {
            EventType::Walk => app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $event->organiser_id]),
            EventType::Social => app(SaveSocialDetails::class)->handle($event, []),
            EventType::Holiday => app(SaveHolidayDetails::class)->handle($event, []),
        };

        return $event->refresh();
    }
}
