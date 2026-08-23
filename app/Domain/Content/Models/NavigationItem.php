<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['label', 'url', 'sort_order', 'enabled', 'module_key', 'open_in_new_tab'])]
final class NavigationItem extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $item): void {
            if (! self::isAllowedUrl($item->url)) {
                throw ValidationException::withMessages(['url' => 'Use a site path or a secure external URL.']);
            }
        });
    }

    public static function isAllowedUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '..'))
            || (filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https');
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'open_in_new_tab' => 'boolean', 'sort_order' => 'integer'];
    }
}
