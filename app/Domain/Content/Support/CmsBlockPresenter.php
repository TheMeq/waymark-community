<?php

namespace App\Domain\Content\Support;

use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\SiteMediaPresenter;

final readonly class CmsBlockPresenter
{
    public function __construct(private CmsBlockRenderer $richText, private SiteMediaPresenter $media) {}

    /** @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    public function present(array $blocks): array
    {
        $ids = collect($blocks)->flatMap(fn (array $block): array => array_values(array_filter([
            $block['media_id'] ?? null,
            ...((array) ($block['media_ids'] ?? [])),
        ], 'is_int')))->unique()->values();
        $media = SiteMedia::query()->whereKey($ids)->get()->keyBy('id');

        return collect($blocks)->map(function (array $block) use ($media): array {
            if ($block['type'] === 'rich_text') {
                $block['html'] = $this->richText->richText((string) ($block['content'] ?? ''));
            }
            if (isset($block['media_id'])) {
                $item = $media->get((int) $block['media_id']);
                $block['media'] = $item instanceof SiteMedia ? $this->media->present($item) : null;
            }
            if ($block['type'] === 'gallery') {
                $block['media'] = collect((array) ($block['media_ids'] ?? []))->map(function ($id) use ($media) {
                    $item = $media->get((int) $id);

                    return $item instanceof SiteMedia ? $this->media->present($item) : null;
                })->filter()->values()->all();
            }

            return $block;
        })->all();
    }
}
