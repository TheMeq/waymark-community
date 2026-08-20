<?php

namespace Database\Factories;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $startsAt = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'type' => EventType::Walk,
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+4 hours'),
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
            'completion_override' => null,
            'organiser_id' => User::factory(),
        ];
    }
}
