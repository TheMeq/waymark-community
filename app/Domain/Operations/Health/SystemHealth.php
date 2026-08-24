<?php

namespace App\Domain\Operations\Health;

use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\Operations\Scheduling\SchedulerHeartbeat;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SystemHealth
{
    public function __construct(private SchedulerHeartbeat $scheduler) {}

    public function report(bool $https): SystemHealthReport
    {
        $checks = [
            new HealthCheck('platform', 'Platform', 'healthy', 'Waymark Community '.config('waymark.version', 'development').' is running.'),
            new HealthCheck('php', 'PHP', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'healthy' : 'critical', 'PHP '.PHP_VERSION.' is active.'),
            $this->database(),
            new HealthCheck('storage', 'Private storage', is_writable(storage_path('app/private')) ? 'healthy' : 'critical', is_writable(storage_path('app/private')) ? 'Private storage is writable.' : 'Private storage is not writable.'),
            $this->diskSpace(),
            $this->email(),
            $this->scheduler(),
            $this->backups(),
            new HealthCheck('https', 'HTTPS', $https ? 'healthy' : 'warning', $https ? 'The administration request is encrypted.' : 'HTTPS was not detected for this request.'),
            $this->updates(),
            $this->missingMedia(),
        ];

        return new SystemHealthReport($checks);
    }

    private function database(): HealthCheck
    {
        try {
            DB::select('SELECT 1');

            return new HealthCheck('database', 'Database', 'healthy', ucfirst((string) DB::getDriverName()).' is responding.');
        } catch (Throwable) {
            return new HealthCheck('database', 'Database', 'critical', 'The database is not responding.');
        }
    }

    private function diskSpace(): HealthCheck
    {
        $free = @disk_free_space(storage_path());
        if (! is_float($free) && ! is_int($free)) {
            return new HealthCheck('disk', 'Disk space', 'warning', 'Available disk space could not be measured.');
        }

        $megabytes = (int) floor($free / 1024 / 1024);
        $status = $megabytes < 100 ? 'critical' : ($megabytes < 500 ? 'warning' : 'healthy');

        return new HealthCheck('disk', 'Disk space', $status, number_format($megabytes).' MB is available to Waymark.');
    }

    private function email(): HealthCheck
    {
        $mailer = (string) config('mail.default');
        $configured = $mailer !== '' && ($mailer !== 'smtp' || trim((string) config('mail.mailers.smtp.host')) !== '');

        return new HealthCheck('email', 'Email', $configured ? 'healthy' : 'warning', $configured ? ucfirst($mailer).' delivery is configured.' : 'Email delivery is not configured.');
    }

    private function scheduler(): HealthCheck
    {
        $status = $this->scheduler->status();

        return new HealthCheck('scheduler', 'Scheduler', $status->healthy() ? 'healthy' : 'warning', $status->message);
    }

    private function backups(): HealthCheck
    {
        $backups = BackupRun::query()->where('status', 'completed')->count();

        return new HealthCheck('backups', 'Backups', $backups === 0 ? 'warning' : 'healthy', $backups === 0 ? 'No completed backup was found.' : 'A completed backup is available.');
    }

    private function updates(): HealthCheck
    {
        $configured = trim((string) config('waymark.updates.metadata_url')) !== '';

        return new HealthCheck('updates', 'Updates', $configured ? 'healthy' : 'warning', $configured ? 'Stable release checks are configured.' : 'A stable release feed is not configured.');
    }

    private function missingMedia(): HealthCheck
    {
        $count = MissingMediaRepair::query()->where('status', 'queued')->count();

        return new HealthCheck('missing-media', 'Missing media', $count > 0 ? 'critical' : 'healthy', $count > 0 ? $count.' missing file(s) need repair.' : 'No missing files are queued for repair.');
    }
}
