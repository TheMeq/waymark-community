<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Models\NavigationItem;
use Illuminate\Support\Facades\Validator;

final class CmsBlockValidator
{
    private const TYPES = ['rich_text', 'image_text', 'callout', 'faq', 'quote', 'cta', 'button_group', 'document_list', 'gallery', 'statistics', 'timeline', 'columns'];

    /** @param array<int, mixed> $blocks
     * @return array<int, array<string, mixed>>
     */
    public function validate(array $blocks): array
    {
        $validator = Validator::make(['blocks' => $blocks], [
            'blocks' => ['array', 'max:40'],
            'blocks.*' => ['required', 'array'],
            'blocks.*.type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'blocks.*.content' => ['nullable', 'string', 'max:50000'],
            'blocks.*.heading' => ['nullable', 'string', 'max:255'],
            'blocks.*.body' => ['nullable', 'string', 'max:10000'],
            'blocks.*.quote' => ['nullable', 'string', 'max:5000'],
            'blocks.*.attribution' => ['nullable', 'string', 'max:255'],
            'blocks.*.media_id' => ['nullable', 'integer', 'exists:site_media,id'],
            'blocks.*.media_ids' => ['nullable', 'array', 'max:6'],
            'blocks.*.media_ids.*' => ['integer', 'distinct', 'exists:site_media,id'],
            'blocks.*.label' => ['nullable', 'string', 'max:255'],
            'blocks.*.url' => ['nullable', 'string', 'max:2048'],
            'blocks.*.items' => ['nullable', 'array', 'max:30'],
            'blocks.*.items.*' => ['array'],
            'blocks.*.items.*.question' => ['nullable', 'string', 'max:500'],
            'blocks.*.items.*.answer' => ['nullable', 'string', 'max:5000'],
            'blocks.*.items.*.label' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.value' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.body' => ['nullable', 'string', 'max:5000'],
            'blocks.*.items.*.url' => ['nullable', 'string', 'max:2048'],
        ]);
        $validator->after(function ($validator) use ($blocks): void {
            foreach ($blocks as $index => $block) {
                if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                    continue;
                }
                $items = is_array($block['items'] ?? null) ? $block['items'] : [];
                $required = match ($block['type']) {
                    'rich_text' => filled($block['content'] ?? null),
                    'image_text' => isset($block['media_id']) && (filled($block['heading'] ?? null) || filled($block['body'] ?? null)),
                    'callout' => filled($block['heading'] ?? null) || filled($block['body'] ?? null),
                    'faq' => $items !== [] && collect($items)->every(fn ($item): bool => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null)),
                    'quote' => filled($block['quote'] ?? null),
                    'cta' => filled($block['label'] ?? null) && self::allowedUrl($block['url'] ?? null),
                    'button_group' => count($items) >= 1 && count($items) <= 4 && self::linksAreValid($items),
                    'document_list' => count($items) >= 1 && count($items) <= 12 && self::linksAreValid($items),
                    'gallery' => count((array) ($block['media_ids'] ?? [])) >= 1 && count((array) ($block['media_ids'] ?? [])) <= 6,
                    'statistics' => count($items) >= 1 && count($items) <= 6 && collect($items)->every(fn ($item): bool => is_array($item) && filled($item['label'] ?? null) && filled($item['value'] ?? null)),
                    'timeline' => count($items) >= 1 && count($items) <= 12 && collect($items)->every(fn ($item): bool => is_array($item) && filled($item['label'] ?? null) && filled($item['body'] ?? null)),
                    'columns' => count($items) >= 2 && count($items) <= 3 && collect($items)->every(fn ($item): bool => is_array($item) && filled($item['body'] ?? null)),
                    default => false,
                };
                if (! $required) {
                    $validator->errors()->add("blocks.{$index}", 'Complete the required fields for this approved block type.');
                }
            }
        });
        $validated = $validator->validate();

        return array_values($validated['blocks'] ?? []);
    }

    private static function allowedUrl(mixed $url): bool
    {
        return is_string($url) && NavigationItem::isAllowedUrl($url);
    }

    /** @param array<int, mixed> $items */
    private static function linksAreValid(array $items): bool
    {
        return collect($items)->every(fn ($item): bool => is_array($item) && filled($item['label'] ?? null) && self::allowedUrl($item['url'] ?? null));
    }
}
