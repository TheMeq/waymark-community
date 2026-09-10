<?php

namespace App\Domain\Walks\Presentation;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\Walks\Data\WalkFeaturedImage;
use App\Domain\Walks\Models\Walk;

final readonly class WalkFeaturedImagePresenter
{
    public function __construct(private SiteMediaPresenter $mediaPresenter) {}

    public function present(Walk $walk, string $variant = 'master'): ?WalkFeaturedImage
    {
        $media = $walk->featuredMedia;
        $managed = $media === null ? null : $this->mediaPresenter->present($media, $variant);
        $alt = $this->compatibilityAlt($walk, $media?->alt_text);

        if ($managed !== null && $alt !== null) {
            return new WalkFeaturedImage($managed->url, $alt);
        }

        $externalUrl = PublicImageReference::resolve($walk->featured_image_path);
        if ($externalUrl === null) {
            return null;
        }

        return new WalkFeaturedImage(
            $externalUrl,
            $alt ?? $this->titleAlt($walk),
        );
    }

    private function compatibilityAlt(Walk $walk, ?string $mediaAlt): ?string
    {
        foreach ([$walk->featured_image_alt_text, $mediaAlt, $this->demoAlt($walk->featured_image_path)] as $candidate) {
            $alt = is_string($candidate) ? trim($candidate) : '';

            if ($alt !== '') {
                return $alt;
            }
        }

        return null;
    }

    private function demoAlt(?string $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $path = parse_url($reference, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#\A/images/demo/([^/]+)\z#i', $path, $matches) !== 1) {
            return null;
        }

        return match (strtolower($matches[1])) {
            'hero-walkers.png', 'hero-walkers-768.webp', 'hero-walkers-1536.webp' => 'A group walking together across open moorland',
            'woodland-walk.png', 'woodland-walk-768.webp', 'woodland-walk-1536.webp' => 'Walkers following a path through green woodland',
            'lakeside-friends.png', 'lakeside-friends-768.webp', 'lakeside-friends-1536.webp' => 'Friends pausing beside an upland lake',
            'coastal-weekend.png', 'coastal-weekend-768.webp', 'coastal-weekend-1536.webp' => 'A walking group following a coastal path',
            default => null,
        };
    }

    private function titleAlt(Walk $walk): string
    {
        return trim((string) $walk->event?->title).' featured image';
    }
}
