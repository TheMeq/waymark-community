<?php

namespace App\Domain\Operations\Installation;

final readonly class SharedHostingEnvironment
{
    public function __construct(
        public string $documentRoot,
        public string $applicationRoot,
        public string $publicPath,
        public string $environmentPath,
        public bool $production,
        public bool $debug,
        public bool $protectedPublicHtmlLayout = false,
    ) {}
}
