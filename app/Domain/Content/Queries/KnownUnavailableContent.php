<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Data\UnavailableContent;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Governance\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class KnownUnavailableContent
{
    public function find(string $path): ?UnavailableContent
    {
        foreach ($this->patterns() as [$pattern, $table, $model, $type]) {
            if (preg_match($pattern, $path, $matches) !== 1 || ! Schema::hasTable($table)) {
                continue;
            }
            $query = $model::query();
            if (in_array($model, [CmsPage::class, NewsArticle::class, Document::class], true)) {
                $query->withTrashed();
            }
            if ($type instanceof EventType) {
                $query->where('type', $type);
            }
            /** @var Model|null $record */
            $record = $query->where('slug', $matches[1])->first();
            if ($record instanceof Model && $this->isKnownUnavailable($record)) {
                return new UnavailableContent((string) $record->getAttribute('title'));
            }
        }

        return null;
    }

    private function isKnownUnavailable(Model $record): bool
    {
        return match (true) {
            $record instanceof CmsPage => $record->archived_at !== null,
            $record instanceof NewsArticle => $record->deleted_at !== null,
            $record instanceof Document => $record->deleted_at !== null && $record->visibility === 'public',
            $record instanceof Event => $record->status?->value === 'archived',
            default => false,
        };
    }

    /** @return list<array{string,string,class-string<Model>,?EventType}> */
    private function patterns(): array
    {
        return [
            ['#\Apages/([^/]+)\z#', 'cms_pages', CmsPage::class, null],
            ['#\Anews/([^/]+)\z#', 'news_articles', NewsArticle::class, null],
            ['#\Adocuments/([^/]+)\z#', 'documents', Document::class, null],
            ['#\Aphotos/albums/([^/]+)\z#', 'special_albums', SpecialAlbum::class, null],
            ['#\Awalks/([^/]+)\z#', 'events', Event::class, EventType::Walk],
            ['#\Asocials/([^/]+)\z#', 'events', Event::class, EventType::Social],
            ['#\Aweekends/([^/]+)\z#', 'events', Event::class, EventType::Holiday],
        ];
    }
}
