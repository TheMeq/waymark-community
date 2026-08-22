<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SaveSpecialAlbum
{
    public function handle(User $actor, ?SpecialAlbum $album, string $title, string $slug, ?string $description): SpecialAlbum
    {
        $this->authorize($actor);

        $attributes = Validator::make([
            'title' => trim($title),
            'slug' => trim($slug),
            'description' => ($description = trim((string) $description)) === '' ? null : $description,
        ], [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique(SpecialAlbum::class, 'slug')->ignore($album)],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($album, $attributes): SpecialAlbum {
            if ($album === null) {
                return SpecialAlbum::query()->create($attributes);
            }

            $locked = SpecialAlbum::query()->lockForUpdate()->findOrFail($album->id);
            $locked->fill($attributes)->save();

            return $locked;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManageSpecialAlbums)) {
            throw new AuthorizationException;
        }
    }
}
