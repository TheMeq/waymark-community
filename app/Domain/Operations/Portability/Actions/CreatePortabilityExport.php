<?php

namespace App\Domain\Operations\Portability\Actions;

use App\Domain\Operations\Backups\PortableDatabaseExporter;
use App\Domain\Operations\Portability\Models\PortabilityExportRun;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

final readonly class CreatePortabilityExport
{
    private const array EXCLUDED_PREFIXES = [
        'local' => ['account-exports/', 'portability-exports/'],
        'public' => [],
    ];

    public function __construct(private PortableDatabaseExporter $databaseExporter) {}

    public function handle(): PortabilityExportRun
    {
        $run = $this->start();
        do {
            $run = $this->advance($run, 500);
        } while (in_array($run->status, ['queued', 'running'], true));

        if ($run->status !== 'completed') {
            throw new RuntimeException('Portability export generation failed. Review system health and try again.');
        }

        return $run;
    }

    public function start(): PortabilityExportRun
    {
        if (PortabilityExportRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw new RuntimeException('A portability export is already in progress.');
        }

        return PortabilityExportRun::query()->create([
            'status' => 'queued',
            'stage' => 'snapshot',
            'work_state' => ['staging_token' => bin2hex(random_bytes(16))],
            'retryable' => false,
            'started_at' => now(),
        ]);
    }

    public function advance(PortabilityExportRun $run, int $componentLimit = 25): PortabilityExportRun
    {
        $run->refresh();
        if (! in_array($run->status, ['queued', 'running'], true)) {
            return $run;
        }

        try {
            return match ($run->stage) {
                'snapshot' => $this->snapshot($run),
                'copy_files' => $this->copyFiles($run, $componentLimit),
                'prepare_archive' => $this->prepareArchive($run),
                'archive' => $this->archive($run, $componentLimit),
                'finalise' => $this->finalise($run),
                default => throw new RuntimeException('The persisted portability export stage is invalid.'),
            };
        } catch (Throwable $exception) {
            report($exception);
            $this->cleanDirectory($this->stagingDirectory($run));
            $run->update([
                'status' => 'failed',
                'retryable' => true,
                'failure_message' => 'Portability export generation failed. Review system health and try again.',
                'last_progress_at' => now(),
            ]);

            throw new RuntimeException('Portability export generation failed. Review system health and try again.', previous: $exception);
        }
    }

    public function retry(PortabilityExportRun $run): PortabilityExportRun
    {
        if ($run->status !== 'failed' || ! $run->retryable) {
            throw new RuntimeException('Only a failed retryable portability export can be restarted.');
        }

        $this->cleanDirectory($this->stagingDirectory($run));
        $run->update([
            'status' => 'queued',
            'stage' => 'snapshot',
            'work_state' => ['staging_token' => bin2hex(random_bytes(16))],
            'retryable' => false,
            'failure_message' => null,
            'started_at' => now(),
            'completed_at' => null,
            'last_progress_at' => null,
        ]);

        return $run->fresh();
    }

    private function snapshot(PortabilityExportRun $run): PortabilityExportRun
    {
        $directory = $this->stagingDirectory($run);
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The portability export staging directory could not be created.');
        }

        $databasePath = $directory.DIRECTORY_SEPARATOR.'database.jsonl';
        $this->databaseExporter->export($databasePath, 100);
        $files = [];
        foreach (array_keys(self::EXCLUDED_PREFIXES) as $diskName) {
            foreach (Storage::disk($diskName)->allFiles() as $path) {
                if (! is_string($path) || $this->isExcluded($diskName, $path)) {
                    continue;
                }
                $source = Storage::disk($diskName)->path($path);
                if (! is_file($source)) {
                    continue;
                }
                $files[] = [
                    'disk' => $diskName,
                    'path' => $path,
                    'sha256' => hash_file('sha256', $source),
                    'size_bytes' => filesize($source),
                ];
            }
        }

        usort($files, fn (array $left, array $right): int => [$left['disk'], $left['path']] <=> [$right['disk'], $right['path']]);
        $run->update([
            'status' => 'running',
            'stage' => 'copy_files',
            'work_state' => [
                'staging_token' => $run->work_state['staging_token'],
                'files' => $files,
                'file_index' => 0,
                'archive_index' => 0,
                'components' => [$this->component('records/database.jsonl', $databasePath)],
            ],
            'last_progress_at' => now(),
        ]);

        return $run->fresh();
    }

    private function copyFiles(PortabilityExportRun $run, int $limit): PortabilityExportRun
    {
        $state = $run->work_state;
        $files = $state['files'] ?? [];
        $index = (int) ($state['file_index'] ?? 0);
        $end = min(count($files), $index + max(1, $limit));

        for (; $index < $end; $index++) {
            $file = $files[$index];
            $source = Storage::disk($file['disk'])->path($file['path']);
            if (! is_file($source)
                || filesize($source) !== $file['size_bytes']
                || ! hash_equals($file['sha256'], hash_file('sha256', $source))) {
                throw new RuntimeException('A stored file changed during portability export generation.');
            }

            $archivePath = 'files/'.$file['disk'].'/'.$file['path'];
            $snapshot = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archivePath);
            if (! is_dir(dirname($snapshot)) && ! mkdir(dirname($snapshot), 0700, true) && ! is_dir(dirname($snapshot))) {
                throw new RuntimeException('A portability export staging directory could not be created.');
            }
            if (! copy($source, $snapshot)
                || filesize($snapshot) !== $file['size_bytes']
                || ! hash_equals($file['sha256'], hash_file('sha256', $snapshot))) {
                throw new RuntimeException('A stored file could not be copied consistently.');
            }
            $state['components'][] = [
                'archive_path' => $archivePath,
                'source_path' => $snapshot,
                'sha256' => $file['sha256'],
                'size_bytes' => $file['size_bytes'],
            ];
        }

        $state['file_index'] = $index;
        $run->update([
            'stage' => $index >= count($files) ? 'prepare_archive' : 'copy_files',
            'work_state' => $state,
            'last_progress_at' => now(),
        ]);

        return $run->fresh();
    }

    private function prepareArchive(PortabilityExportRun $run): PortabilityExportRun
    {
        $state = $run->work_state;
        $manifestPath = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = [
            'format' => 'waymark-portability',
            'format_version' => 1,
            'created_at' => now('UTC')->toIso8601String(),
            'waymark_version' => (string) config('waymark.version'),
            'database_driver' => (string) config('database.default'),
            'components' => array_map(
                fn (array $component): array => [
                    'path' => $component['archive_path'],
                    'sha256' => $component['sha256'],
                    'size_bytes' => $component['size_bytes'],
                ],
                $state['components'],
            ),
        ];
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($manifestPath, $encoded."\n", LOCK_EX) === false) {
            throw new RuntimeException('The portability export manifest could not be written.');
        }

        $archive = new ZipArchive;
        $archivePath = $this->stagingDirectory($run).DIRECTORY_SEPARATOR.'portability.zip';
        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
            || ! $archive->addFile($manifestPath, 'manifest.json')
            || ! $archive->close()) {
            throw new RuntimeException('The portability archive could not be prepared.');
        }

        $state['archive_index'] = 0;
        $run->update(['stage' => 'archive', 'work_state' => $state, 'last_progress_at' => now()]);

        return $run->fresh();
    }

    private function archive(PortabilityExportRun $run, int $limit): PortabilityExportRun
    {
        $state = $run->work_state;
        $components = $state['components'];
        $index = (int) ($state['archive_index'] ?? 0);
        $end = min(count($components), $index + max(1, $limit));
        $archive = new ZipArchive;
        if ($archive->open($this->stagingDirectory($run).DIRECTORY_SEPARATOR.'portability.zip') !== true) {
            throw new RuntimeException('The portability archive could not be continued.');
        }

        try {
            for (; $index < $end; $index++) {
                $component = $components[$index];
                if (! $archive->addFile($component['source_path'], $component['archive_path'])) {
                    throw new RuntimeException('A portability component could not be archived.');
                }
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('The portability archive could not be finalised.');
            }
        }

        $state['archive_index'] = $index;
        $run->update([
            'stage' => $index >= count($components) ? 'finalise' : 'archive',
            'work_state' => $state,
            'last_progress_at' => now(),
        ]);

        return $run->fresh();
    }

    private function finalise(PortabilityExportRun $run): PortabilityExportRun
    {
        $stagingDirectory = $this->stagingDirectory($run);
        $archivePath = $stagingDirectory.DIRECTORY_SEPARATOR.'portability.zip';
        $filename = 'portability-exports/waymark-portability-'.now('UTC')->format('Ymd-His').'-'.$run->id.'.zip';
        $stream = fopen($archivePath, 'rb');
        try {
            if ($stream === false || ! Storage::disk('local')->writeStream($filename, $stream)) {
                throw new RuntimeException('The portability export could not be stored.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $run->update([
            'status' => 'completed',
            'stage' => 'completed',
            'work_state' => null,
            'retryable' => false,
            'storage_disk' => 'local',
            'storage_path' => $filename,
            'sha256' => hash_file('sha256', $archivePath),
            'size_bytes' => filesize($archivePath),
            'completed_at' => now(),
            'last_progress_at' => now(),
        ]);
        $this->cleanDirectory($stagingDirectory);

        return $run->fresh();
    }

    /** @return array{archive_path: string, source_path: string, sha256: string, size_bytes: int} */
    private function component(string $archivePath, string $sourcePath): array
    {
        return [
            'archive_path' => $archivePath,
            'source_path' => $sourcePath,
            'sha256' => hash_file('sha256', $sourcePath),
            'size_bytes' => filesize($sourcePath),
        ];
    }

    private function isExcluded(string $disk, string $path): bool
    {
        return collect(self::EXCLUDED_PREFIXES[$disk])->contains(
            fn (string $prefix): bool => str_starts_with($path, $prefix),
        );
    }

    private function stagingDirectory(PortabilityExportRun $run): string
    {
        $token = $run->work_state['staging_token'] ?? null;
        if (! is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            throw new RuntimeException('The persisted portability export staging identity is invalid.');
        }

        return storage_path('framework/cache/waymark-portability-'.$token);
    }

    private function cleanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
