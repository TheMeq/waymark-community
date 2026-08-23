<?php

namespace App\Domain\Operations\Support;

use App\Domain\Content\Models\NavigationItem;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class BrandingConfigurationValidator
{
    private const ATTRIBUTES = [
        'group_name',
        'short_name',
        'contact_email',
        'logo_path',
        'favicon_path',
        'hero_default_path',
        'primary_colour',
        'accent_colour',
        'typography_option',
        'social_links',
        'terminology',
        'affiliation_name',
        'affiliation_url',
    ];

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function validate(array $values): array
    {
        $validated = validator(Arr::only($values, self::ATTRIBUTES), [
            'group_name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'favicon_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'hero_default_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'primary_colour' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_colour' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'typography_option' => ['sometimes', 'nullable', 'in:instrument,system'],
            'social_links' => ['sometimes', 'nullable', 'array', 'max:10'],
            'terminology' => ['sometimes', 'nullable', 'array', 'max:5'],
            'affiliation_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'affiliation_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ])->validate();

        $allowedTerms = ['walks', 'members', 'join', 'holidays', 'gallery'];
        $terms = (array) ($validated['terminology'] ?? []);
        if (array_diff(array_keys($terms), $allowedTerms) !== []) {
            throw ValidationException::withMessages(['terminology' => 'Use only the approved public terminology keys.']);
        }
        foreach ($terms as $label) {
            if (! is_string($label) || mb_strlen(trim($label)) > 60) {
                throw ValidationException::withMessages(['terminology' => 'Use concise public terminology labels.']);
            }
        }

        foreach ((array) ($validated['social_links'] ?? []) as $label => $url) {
            if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 60 || ! is_string($url) || ! NavigationItem::isAllowedUrl($url)) {
                throw ValidationException::withMessages(['social_links' => 'Each social link needs a short label and safe URL.']);
            }
        }

        foreach (['logo_path', 'favicon_path', 'hero_default_path', 'affiliation_url'] as $attribute) {
            $url = $validated[$attribute] ?? null;
            if (filled($url) && ! NavigationItem::isAllowedUrl($url)) {
                throw ValidationException::withMessages([$attribute => 'Use a site path or secure external URL.']);
            }
        }

        return $validated;
    }
}
