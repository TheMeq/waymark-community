<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateWalkRecap
{
    /** @param array<string, mixed> $attributes */
    public function handle(Walk $walk, User $actor, array $attributes): Walk
    {
        $walk->load('event');
        Gate::forUser($actor)->authorize('recap', $walk);

        if (! $walk->event->isCompleted()) {
            throw ValidationException::withMessages([
                'walk' => 'A recap can only be added after the walk has completed.',
            ]);
        }

        $validated = Validator::make($this->normalise($attributes), [
            'recap' => ['nullable', 'string', 'max:20000', 'not_regex:/<[^>]*>/'],
            'highlights' => ['nullable', 'string', 'max:5000', 'not_regex:/<[^>]*>/'],
        ])->validate();

        $walk->forceFill([
            'recap' => $validated['recap'] ?? null,
            'highlights' => $validated['highlights'] ?? null,
        ])->save();

        return $walk->refresh()->load('event');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        foreach (['recap', 'highlights'] as $field) {
            if (is_string($attributes[$field] ?? null)) {
                $attributes[$field] = ($value = trim($attributes[$field])) === '' ? null : $value;
            }
        }

        return $attributes;
    }
}
