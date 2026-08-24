<?php

namespace App\Domain\Operations\Scheduling;

use App\Domain\Operations\Scheduling\Contracts\FallbackRunner;
use App\Domain\Operations\Scheduling\Contracts\FallbackWorkload;
use Illuminate\Support\Carbon;

final readonly class RunFallbackWork implements FallbackRunner
{
    public function __construct(
        private SchedulerHeartbeat $heartbeat,
        private FallbackWorkload $workload,
        private string $statePath,
        private int $cooldownMinutes,
    ) {}

    public function handle(string $trigger, ?Carbon $now = null): FallbackRunResult
    {
        $now ??= now('UTC');

        if ($this->heartbeat->status($now)->healthy()) {
            return new FallbackRunResult(false, 'scheduler-healthy');
        }

        $previous = $this->lastRun();
        if ($previous !== null && $previous->diffInMinutes($now) < max(1, $this->cooldownMinutes)) {
            return new FallbackRunResult(false, 'cooldown');
        }

        $this->claim($trigger, $now);

        return new FallbackRunResult(true, 'scheduler-unavailable', $this->workload->run());
    }

    private function lastRun(): ?Carbon
    {
        if (! is_file($this->statePath)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($this->statePath), true);

        try {
            return is_array($payload) && is_string($payload['recorded_at'] ?? null)
                ? Carbon::parse($payload['recorded_at'])->utc()
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function claim(string $trigger, Carbon $now): void
    {
        $directory = dirname($this->statePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $temporaryPath = $this->statePath.'.tmp';
        file_put_contents($temporaryPath, json_encode([
            'trigger' => $trigger,
            'recorded_at' => $now->copy()->utc()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
        @chmod($temporaryPath, 0600);
        rename($temporaryPath, $this->statePath);
    }
}
