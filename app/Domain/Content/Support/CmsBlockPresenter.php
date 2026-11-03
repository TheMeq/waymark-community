<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\SiteMedia\Data\SiteMediaPresentation;
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
            if ($block['type'] === 'image_text') {
                $block['media'] ??= null;
                if ($block['media'] !== null && filled($block['image_alt_text'] ?? null)) {
                    $image = $block['media'];
                    $block['media'] = new SiteMediaPresentation($image->id, $image->url, $block['image_alt_text'], $image->width, $image->height, $image->focalPointX, $image->focalPointY);
                }
                if ($block['media'] === null && ($url = PublicImageReference::resolve($block['image_path'] ?? null)) !== null) {
                    $block['media'] = new SiteMediaPresentation(0, $url, $block['image_alt_text'] ?? '', 1, 1, 0.5, 0.5);
                }
            }
            if ($block['type'] === 'gallery') {
                $block['media'] = collect((array) ($block['media_ids'] ?? []))->map(function ($id) use ($media) {
                    $item = $media->get((int) $id);

                    return $item instanceof SiteMedia ? $this->media->present($item) : null;
                })->filter()->values()->all();
            }
            if (isset($block['url'])) {
                $block['url'] = PublicUrl::resolve($block['url']);
            }
            if (isset($block['items']) && in_array($block['type'], ['button_group', 'document_list'], true)) {
                $block['items'] = collect((array) $block['items'])->map(function (array $item): array {
                    $item['url'] = PublicUrl::resolve($item['url'] ?? null);

                    return $item;
                })->all();
            }

            return $block;
        })->all();
    }
}
