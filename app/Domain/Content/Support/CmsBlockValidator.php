<?php

namespace App\Domain\Content\Support;

use Illuminate\Support\Facades\Validator;

final class CmsBlockValidator
{
    private const TYPES = ['rich_text', 'image_text', 'callout', 'faq', 'quote', 'cta', 'button_group', 'document_list', 'gallery', 'statistics', 'timeline', 'columns'];

    /** @param array<int, mixed> $blocks
     * @return array<int, array<string, mixed>>
     */
    public function validate(array $blocks): array
    {
        $validated = Validator::make(['blocks' => $blocks], [
            'blocks' => ['array', 'max:40'],
            'blocks.*' => ['required', 'array'],
            'blocks.*.type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'blocks.*.content' => ['nullable', 'string', 'max:50000'],
            'blocks.*.heading' => ['nullable', 'string', 'max:255'],
            'blocks.*.body' => ['nullable', 'string', 'max:10000'],
            'blocks.*.quote' => ['nullable', 'string', 'max:5000'],
            'blocks.*.attribution' => ['nullable', 'string', 'max:255'],
            'blocks.*.media_id' => ['nullable', 'integer', 'exists:site_media,id'],
            'blocks.*.items' => ['nullable', 'array', 'max:30'],
            'blocks.*.items.*' => ['array'],
            'blocks.*.items.*.question' => ['nullable', 'string', 'max:500'],
            'blocks.*.items.*.answer' => ['nullable', 'string', 'max:5000'],
            'blocks.*.items.*.label' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.value' => ['nullable', 'string', 'max:255'],
            'blocks.*.items.*.url' => ['nullable', 'string', 'max:2048'],
        ])->validate();

        return array_values($validated['blocks'] ?? []);
    }
}
