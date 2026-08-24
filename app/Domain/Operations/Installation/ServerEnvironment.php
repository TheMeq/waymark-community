<?php

namespace App\Domain\Operations\Installation;

final readonly class ServerEnvironment
{
    /**
     * @param  list<string>  $extensions
     * @param  array<string, bool>  $writableDirectories
     */
    public function __construct(
        public string $phpVersion,
        public array $extensions,
        public array $writableDirectories,
        public int $uploadLimitBytes,
        public int $postLimitBytes,
        public ?string $imageLibrary,
        public bool $https,
        public bool $debug,
        public bool $cronAvailable,
    ) {}
}
