<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Scheduling\Contracts\FallbackWorkload;
use App\Domain\Operations\Scheduling\RunFallbackWork;
use App\Domain\Operations\Scheduling\SchedulerHeartbeat;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class FallbackWorkTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-fallback-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['heartbeat.json', 'fallback.json', 'fallback.json.tmp'] as $file) {
            $path = $this->directory.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_stale_scheduler_runs_bounded_fallback_without_faking_a_cron_heartbeat(): void
    {
        $workload = new class implements FallbackWorkload
        {
            public int $runs = 0;

            public function run(): array
            {
                $this->runs++;

                return ['deferred_photos' => 1, 'personal_data_exports' => 1];
            }
        };
        $heartbeat = new SchedulerHeartbeat($this->heartbeatPath(), 5);
        $runner = new RunFallbackWork($heartbeat, $workload, $this->fallbackPath(), 5);
        $now = Carbon::parse('2026-08-24 12:00:00', 'UTC');

        $first = $runner->handle('request', $now);
        $second = $runner->handle('request', $now->copy()->addMinute());

        $this->assertTrue($first->ran);
        $this->assertSame(1, $workload->runs);
        $this->assertFalse($second->ran);
        $this->assertSame('cooldown', $second->reason);
        $this->assertSame('missing', $heartbeat->status($now)->state);
    }

    public function test_healthy_cron_skips_fallback_work(): void
    {
        $workload = new class implements FallbackWorkload
        {
            public int $runs = 0;

            public function run(): array
            {
                $this->runs++;

                return [];
            }
        };
        $heartbeat = new SchedulerHeartbeat($this->heartbeatPath(), 5);
        $now = Carbon::parse('2026-08-24 12:00:00', 'UTC');
        $heartbeat->recordCronRun($now);

        $result = (new RunFallbackWork($heartbeat, $workload, $this->fallbackPath(), 5))->handle('manual', $now);

        $this->assertFalse($result->ran);
        $this->assertSame('scheduler-healthy', $result->reason);
        $this->assertSame(0, $workload->runs);
    }

    private function heartbeatPath(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'heartbeat.json';
    }

    private function fallbackPath(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'fallback.json';
    }
}
