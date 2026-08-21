<?php

namespace App\Domain\Gallery\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'slug', 'description'])]
final class SpecialAlbum extends Model
{
    /** @return HasMany<CommunityPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(CommunityPhoto::class);
    }
}
