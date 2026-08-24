<?php

namespace App\Domain\Operations\Updates;

final readonly class ReleaseMetadata
{
    /** @param list<string> $releaseNotes
     * @param  array<string, mixed>  $requirements
     */
    public function __construct(
        public string $version,
        public string $publishedAt,
        public bool $securityRelease,
        public string $summary,
        public array $releaseNotes,
        public string $packageUrl,
        public string $packageSha256,
        public int $packageSizeBytes,
        public array $requirements,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'published_at' => $this->publishedAt,
            'security_release' => $this->securityRelease,
            'summary' => $this->summary,
            'release_notes' => $this->releaseNotes,
            'package_url' => $this->packageUrl,
            'package_sha256' => $this->packageSha256,
            'package_size_bytes' => $this->packageSizeBytes,
            'requirements' => $this->requirements,
        ];
    }
}
