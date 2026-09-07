<?php

namespace App\Filament\Resources\WalkResource\Support;

use App\Domain\Walks\Models\Walk;

final class WalkFormData
{
    /** @return array<string, mixed> */
    public static function from(Walk $walk): array
    {
        $walk->loadMissing(['event', 'coLeaders', 'tags']);

        return [
            ...$walk->attributesToArray(),
            ...$walk->event->only(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']),
            'co_leader_ids' => $walk->coLeaders->modelKeys(),
            'tag_ids' => $walk->tags->modelKeys(),
        ];
    }
}
