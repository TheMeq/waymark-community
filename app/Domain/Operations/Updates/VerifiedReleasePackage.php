<?php

namespace App\Domain\Operations\Updates;

final class VerifiedReleasePackage
{
    /** @param list<string> $files
     * @param  list<string>  $deletes
     */
    public function __construct(
        public readonly string $stagingDirectory,
        public readonly string $version,
        public readonly array $files,
        public readonly array $deletes,
        public readonly string $layout = 'standard',
    ) {}

    public function applicationPath(string $path): string
    {
        return $this->stagingDirectory.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function cleanup(): void
    {
        $this->clean($this->stagingDirectory);
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
