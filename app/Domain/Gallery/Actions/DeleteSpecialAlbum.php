<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteSpecialAlbum
{
    public function handle(User $actor, SpecialAlbum $album): bool
    {
        if (! $actor->hasCapability(ModuleCapability::ManageSpecialAlbums)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($album): bool {
            $locked = SpecialAlbum::query()->lockForUpdate()->findOrFail($album->id);

            if ($locked->photos()->exists()) {
                throw ValidationException::withMessages([
                    'album' => 'This album cannot be deleted while photos reference it.',
                ]);
            }

            return (bool) $locked->delete();
        });
    }
}
