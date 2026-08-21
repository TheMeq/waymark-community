<?php

namespace App\Domain\Socials\Actions;

use App\Domain\Socials\Models\Social;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateSocial
{
    public function __construct(private SaveSocialDetails $saveSocialDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Social $social, User $actor, array $attributes): Social
    {
        Gate::forUser($actor)->authorize('update', $social);
        $social->loadMissing('event');
        $common = Validator::make($attributes, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug,'.$social->event_id],
            'summary' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        return DB::transaction(function () use ($social, $attributes, $common): Social {
            $social->event->fill(Arr::only($common, ['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']));
            $social->event->save();

            return $this->saveSocialDetails->handle($social->event, $attributes);
        });
    }
}
