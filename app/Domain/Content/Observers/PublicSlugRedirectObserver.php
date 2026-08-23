<?php

namespace App\Domain\Content\Observers;

use App\Domain\Content\Actions\RecordAutomaticSlugRedirect;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Governance\Models\Document;
use Illuminate\Database\Eloquent\Model;

final readonly class PublicSlugRedirectObserver
{
    public function updated(Model $model): void
    {
        if (! $model->wasChanged('slug')) {
            return;
        }

        $oldPath = $this->path($model, (string) $model->getOriginal('slug'));
        $newPath = $this->path($model, (string) $model->getAttribute('slug'));
        if ($oldPath !== null && $newPath !== null && $oldPath !== $newPath) {
            app(RecordAutomaticSlugRedirect::class)->handle($oldPath, $newPath);
        }
    }

    private function path(Model $model, string $slug): ?string
    {
        return match (true) {
            $model instanceof CmsPage => '/pages/'.$slug,
            $model instanceof NewsArticle => '/news/'.$slug,
            $model instanceof Document => '/documents/'.$slug,
            $model instanceof SpecialAlbum => '/photos/albums/'.$slug,
            $model instanceof Event => match ($model->type) {
                EventType::Walk => '/walks/'.$slug,
                EventType::Social => '/socials/'.$slug,
                EventType::Holiday => '/weekends/'.$slug,
            },
            default => null,
        };
    }
}
