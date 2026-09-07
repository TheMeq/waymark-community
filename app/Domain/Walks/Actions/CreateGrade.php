<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CreateGrade
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): Grade
    {
        Gate::forUser($actor)->authorize('create', Grade::class);

        $attributes['display_order'] ??= ((int) Grade::query()->max('display_order')) + 1;
        $validated = Validator::make($attributes, [
            'display_order' => ['integer', 'min:0', 'max:65535'],
            'name' => ['required', 'string', 'max:255', 'unique:grades,name'],
            'description' => ['required', 'string', 'max:1000'],
            'colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
        ])->validate();

        return Grade::query()->create($validated);
    }
}
