<?php

namespace App\Domain\Operations\Backups;

final class VerifiedBackup
{
    /** @param array<string, mixed> $manifest */
    public function __construct(
        public readonly string $archivePath,
        public readonly array $manifest,
        private readonly ?string $temporaryPath = null,
    ) {}

    /** @return list<string> */
    public function componentPaths(): array
    {
        return array_values(array_map(
            fn (array $component): string => (string) $component['path'],
            $this->manifest['components'],
        ));
    }

    public function cleanup(): void
    {
        if (is_string($this->temporaryPath)) {
            @unlink($this->temporaryPath);
        }
    }
}
