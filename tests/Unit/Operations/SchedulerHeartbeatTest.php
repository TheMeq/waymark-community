<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Scheduling\SchedulerHeartbeat;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class SchedulerHeartbeatTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-heartbeat-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'heartbeat.json';
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_never_run_fresh_and_stale_scheduler_states_are_distinct(): void
    {
        $heartbeat = new SchedulerHeartbeat($this->path(), 5);
        $now = Carbon::parse('2026-08-24 12:00:00', 'UTC');

        $this->assertSame('missing', $heartbeat->status($now)->state);
        $this->assertStringContainsString('never recorded', $heartbeat->status($now)->message);

        $heartbeat->recordCronRun($now->copy()->subMinutes(2));
        $this->assertSame('healthy', $heartbeat->status($now)->state);

        $heartbeat->recordCronRun($now->copy()->subMinutes(8));
        $status = $heartbeat->status($now);
        $this->assertSame('stale', $status->state);
        $this->assertStringContainsString('8 minutes', $status->message);
        $this->assertSame('cron', json_decode((string) file_get_contents($this->path()), true)['source']);
    }

    private function path(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'heartbeat.json';
    }
}
