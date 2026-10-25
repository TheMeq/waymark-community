<?php

namespace App\Filament\Forms;

use App\Domain\SiteMedia\Actions\SaveContentImage;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use Illuminate\Support\Arr;

trait PreparesContentFormData
{
    protected ?int $previousContentImageId = null;

    protected array $previousBlockImageIds = [];

    protected function prepareResourceData(array $data): array
    {
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $slot = ContentImageSlot::tryFrom(static::getResource()::getModel());
            $this->previousBlockImageIds = collect((array) $this->getRecord()?->getAttribute('blocks'))->pluck('media_id')->filter()->all();
            $data['blocks'] = array_values(array_map(function (array $block) use ($slot): array {
                $block = isset($block['data']) ? ['type' => $block['type'], ...$block['data']] : $block;
                if ($slot !== null && $block['type'] === 'image_text' && isset($block['content_image_source'])) {
                    $block = app(SaveContentImage::class)->prepareBlock(auth()->user(), $slot, $block);
                }

                return $block;
            }, $data['blocks']));
        }

        $slot = ContentImageSlot::tryFrom(static::getResource()::getModel());
        if ($slot !== null && array_key_exists('content_image_source', $data)) {
            $owner = $this->getRecord();
            $this->previousContentImageId = $owner?->{$slot->mediaColumn()};
            $prepared = app(SaveContentImage::class)->prepare(auth()->user(), $slot, $data, $owner);
            $data = [...Arr::except($data, ContentImageFields::stateNames()), ...$prepared];
        }

        return $data;
    }

    protected function completeResourceImage(): void
    {
        $slot = ContentImageSlot::tryFrom(static::getResource()::getModel());
        if ($slot !== null) {
            app(SaveContentImage::class)->complete(auth()->user(), $slot, $this->getRecord(), $this->previousContentImageId);
            app(SaveContentImage::class)->completeBlocks(auth()->user(), $slot, $this->getRecord(), $this->previousBlockImageIds);
        }
    }

    protected function fillResourceData(array $data): array
    {
        $slot = ContentImageSlot::tryFrom(static::getResource()::getModel());
        if ($slot !== null && $this->getRecord() !== null) {
            $data = [...$data, ...ContentImageFields::fill($this->getRecord(), $slot)];
        }
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $data['blocks'] = array_map(function (array $block) use ($slot): array {
                if (isset($block['data'])) {
                    return $block;
                }
                $type = $block['type'];
                if ($slot !== null && $type === 'image_text') {
                    $model = $slot->value;
                    $block = [...$block, ...ContentImageFields::fill(new $model([
                        $slot->mediaColumn() => $block['media_id'] ?? null,
                        $slot->pathColumn() => $block['image_path'] ?? null,
                        $slot->altColumn() => $block['image_alt_text'] ?? null,
                    ]), $slot)];
                }
                unset($block['type']);

                return ['type' => $type, 'data' => $block];
            }, $data['blocks']);
        }

        return $data;
    }
}
