<?php

namespace App\Domain\Content\Observers;

use App\Domain\Content\Support\PublicContentCache;
use Illuminate\Database\Eloquent\Model;

final class PublicContentCacheObserver
{
    public function saved(Model $model): void
    {
        PublicContentCache::forgetFor($model);
    }

    public function deleted(Model $model): void
    {
        PublicContentCache::forgetFor($model);
    }

    public function restored(Model $model): void
    {
        PublicContentCache::forgetFor($model);
    }
}
