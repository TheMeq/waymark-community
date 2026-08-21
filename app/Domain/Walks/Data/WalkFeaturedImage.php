<?php

namespace App\Domain\Walks\Data;

final readonly class WalkFeaturedImage
{
    private function __construct(
        public string $url,
        public string $alt,
    ) {}

    public static function resolve(?string $reference): ?self
    {
        if (! is_string($reference)
            || preg_match('/\A\/images\/demo\/([a-z0-9][a-z0-9._-]*\.(?:avif|jpe?g|png|webp))\z/iD', $reference, $matches) !== 1
            || ! is_file(public_path('images/demo/'.$matches[1]))) {
            return null;
        }

        return new self($reference, self::altFor($matches[1]));
    }

    /** @return array{url: string, alt: string} */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'alt' => $this->alt,
        ];
    }

    private static function altFor(string $filename): string
    {
        return match (strtolower($filename)) {
            'hero-walkers.png' => 'A group walking together across open moorland',
            'woodland-walk.png' => 'Walkers following a path through green woodland',
            'lakeside-friends.png' => 'Friends pausing beside an upland lake',
            'coastal-weekend.png' => 'A walking group following a coastal path',
            default => 'Walkers enjoying a countryside route',
        };
    }
}
