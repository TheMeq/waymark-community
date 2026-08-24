<?php

namespace Tests\Feature\Operations;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SchedulerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_heartbeat_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'waymark:scheduler-heartbeat'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_manual_fallback_command_explains_that_it_does_not_replace_cron(): void
    {
        $exit = Artisan::call('waymark:run-fallback');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('does not provide scheduled guarantees', Artisan::output());
    }

    public function test_shared_host_guide_documents_cron_and_the_limited_fallback_honestly(): void
    {
        $guide = (string) file_get_contents(base_path('docs/deployment/shared-hosting.md'));

        $this->assertStringContainsString('schedule:run', $guide);
        $this->assertStringContainsString('does not make newsletters or reminders timely', $guide);
    }
}
