<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Models\PublicRedirect;
use Illuminate\Validation\ValidationException;

final class PublicRedirectGuard
{
    public function validate(PublicRedirect $redirect): void
    {
        $source = (string) $redirect->source_path;
        $target = (string) $redirect->target_url;

        if (! $this->safeSource($source)) {
            throw ValidationException::withMessages(['source_path' => 'Use one exact public path without wildcards, traversal, query parameters or fragments.']);
        }
        if (! NavigationItem::isAllowedUrl($target) || str_starts_with($target, '/') && (str_contains($target, '?') || str_contains($target, '#'))) {
            throw ValidationException::withMessages(['target_url' => 'Use a safe site path or secure external URL.']);
        }
        if (! in_array((int) $redirect->status_code, [301, 302], true)) {
            throw ValidationException::withMessages(['status_code' => 'Choose a permanent or temporary redirect.']);
        }
        if ($this->localPath($target) === $source || $this->createsLoop($redirect, $source, $target)) {
            throw ValidationException::withMessages(['target_url' => 'This redirect would create a loop.']);
        }
    }

    private function safeSource(string $source): bool
    {
        return mb_strlen($source) <= 512
            && str_starts_with($source, '/') && ! str_starts_with($source, '//') && $source === '/'.ltrim($source, '/')
            && ! str_contains($source, '..') && ! str_contains($source, '*') && ! str_contains($source, '?') && ! str_contains($source, '#')
            && ! preg_match('#\A/(?:admin|account|leader-hub|login|register|logout|up)(?:/|\z)#i', $source);
    }

    private function createsLoop(PublicRedirect $redirect, string $source, string $target): bool
    {
        $next = $this->localPath($target);
        $visited = [$source => true];

        for ($hop = 0; $next !== null && $hop < 25; $hop++) {
            if (isset($visited[$next])) {
                return true;
            }
            $visited[$next] = true;
            $candidate = PublicRedirect::query()->where('enabled', true)->where('source_path', $next)
                ->when($redirect->exists, fn ($query) => $query->whereKeyNot($redirect->getKey()))->first();
            $next = $candidate instanceof PublicRedirect ? $this->localPath($candidate->target_url) : null;
        }

        return $next !== null;
    }

    private function localPath(string $url): ?string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }
}
