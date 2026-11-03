<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CreateTag
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): Tag
    {
        Gate::forUser($actor)->authorize('create', Tag::class);

        $validated = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
        ])->validate();

        return Tag::query()->create($validated);
    }
}
