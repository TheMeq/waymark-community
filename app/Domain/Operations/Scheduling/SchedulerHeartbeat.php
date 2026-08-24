<?php

namespace App\Domain\Operations\Scheduling;

use Illuminate\Support\Carbon;
use RuntimeException;

final readonly class SchedulerHeartbeat
{
    public function __construct(
        private string $path,
        private int $staleAfterMinutes,
    ) {}

    public function recordCronRun(?Carbon $at = null): void
    {
        $at ??= now('UTC');
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the scheduler heartbeat directory.');
        }

        $temporaryPath = $this->path.'.tmp';
        $contents = json_encode(['source' => 'cron', 'recorded_at' => $at->copy()->utc()->toIso8601String()], JSON_UNESCAPED_SLASHES);

        if (! is_string($contents) || file_put_contents($temporaryPath, $contents."\n", LOCK_EX) === false || ! rename($temporaryPath, $this->path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to record the scheduler heartbeat.');
        }

        @chmod($this->path, 0600);
    }

    public function status(?Carbon $now = null): SchedulerStatus
    {
        $now ??= now('UTC');
        $payload = $this->payload();

        if (($payload['source'] ?? null) !== 'cron' || ! is_string($payload['recorded_at'] ?? null)) {
            return new SchedulerStatus('missing', null, 'The scheduler has never recorded a cron run.');
        }

        try {
            $recordedAt = Carbon::parse($payload['recorded_at'])->utc();
        } catch (\Throwable) {
            return new SchedulerStatus('missing', null, 'The scheduler heartbeat could not be read.');
        }

        $ageMinutes = max(0, (int) floor($recordedAt->diffInMinutes($now)));
        if ($ageMinutes <= max(1, $this->staleAfterMinutes)) {
            return new SchedulerStatus('healthy', $recordedAt, 'Cron last ran '.$ageMinutes.' minute(s) ago.');
        }

        return new SchedulerStatus('stale', $recordedAt, 'Cron last ran '.$ageMinutes.' minutes ago and is not currently reliable.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
