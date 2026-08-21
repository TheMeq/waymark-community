<?php

namespace App\Domain\Accounts\Data;

final readonly class LeaderProfilePhoto
{
    private function __construct(public string $url) {}

    public static function resolve(?string $reference): ?self
    {
        if (! is_string($reference)
            || preg_match('/\A\/images\/demo\/([a-z0-9][a-z0-9._-]*\.(?:avif|jpe?g|png|webp))\z/iD', $reference, $matches) !== 1
            || ! is_file(public_path('images/demo/'.$matches[1]))) {
            return null;
        }

        return new self($reference);
    }
}
