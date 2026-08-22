<?php

namespace App\Domain\Gallery\Data;

use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, PublicCommunityPhotoPresentation> */
final readonly class PublicCommunityPhotoPage implements IteratorAggregate
{
    /** @param Collection<int, PublicCommunityPhotoPresentation> $items */
    public function __construct(public Collection $items, public ?string $nextCursor) {}

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function hasMorePages(): bool
    {
        return $this->nextCursor !== null;
    }

    public function nextPageUrl(): ?string
    {
        return $this->nextCursor === null ? null : request()->fullUrlWithQuery(['cursor' => $this->nextCursor]);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
