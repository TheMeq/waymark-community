<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;
use LogicException;

final readonly class StoredGpx
{
    /** @param array<string, mixed> $metadata */
    private function __construct(
        private string $path,
        private array $metadata,
    ) {}

    /** @param array<string, mixed> $metadata */
    public static function fromGeneratedPath(string $path, array $metadata): self
    {
        if (! GpxStoragePath::isGenerated($path)) {
            throw new LogicException('GPX storage paths must use the generated GPX path format.');
        }

        return new self($path, $metadata);
    }

    public static function fromWalk(Walk $walk): ?self
    {
        if (! GpxStoragePath::isGenerated($walk->gpx_path) || ! is_array($walk->gpx_derived_metadata)) {
            return null;
        }

        return new self($walk->gpx_path, $walk->gpx_derived_metadata);
    }

    /** @return array{gpx_path: string, gpx_derived_metadata: array<string, mixed>} */
    public function persistenceAttributes(): array
    {
        return [
            'gpx_path' => $this->path,
            'gpx_derived_metadata' => $this->metadata,
        ];
    }
}
