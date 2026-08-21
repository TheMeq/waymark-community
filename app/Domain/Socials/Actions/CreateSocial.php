<?php

namespace App\Domain\Socials\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Socials\Models\Social;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class CreateSocial
{
    public function __construct(private SaveSocialDetails $saveSocialDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $organiser, array $attributes): Social
    {
        Gate::forUser($organiser)->authorize('create', Social::class);
        $common = Validator::make($attributes, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug'],
            'summary' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        return DB::transaction(function () use ($organiser, $attributes, $common): Social {
            $event = Event::query()->create([
                ...Arr::only($common, ['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']),
                'type' => EventType::Social,
                'status' => EventStatus::Draft,
                'is_public' => false,
                'published_at' => null,
                'organiser_id' => $organiser->id,
            ]);

            return $this->saveSocialDetails->handle($event, $attributes);
        });
    }
}
