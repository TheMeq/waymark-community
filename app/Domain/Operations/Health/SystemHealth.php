<?php

namespace App\Domain\Operations\Health;

use App\Domain\Communication\Support\OutboundEmailStatus;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use App\Domain\Operations\Scheduling\SchedulerHeartbeat;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SystemHealth
{
    public function __construct(
        private SchedulerHeartbeat $scheduler,
        private UpdateStateStore $updateState,
        private PublicApplicationExposureProbe $exposureProbe,
        private OutboundEmailStatus $emailStatus,
        private OpcodeCacheProbe $opcodeCache,
    ) {}

    public function report(bool $https, ?string $baseUrl = null): SystemHealthReport
    {
        $checks = $this->localReport($https)->checks;

        if (config('waymark.deployment_layout') === 'public-html') {
            $checks[] = $this->applicationProtection($baseUrl ?? (string) config('app.url'));
        }

        return new SystemHealthReport($checks);
    }

    public function localReport(bool $https): SystemHealthReport
    {
        $opcacheEnabled = $this->opcodeCache->enabled();

        return new SystemHealthReport([
            new HealthCheck('platform', 'Platform', 'healthy', 'Waymark Community '.config('waymark.version', 'development').' is running.'),
            new HealthCheck('php', 'PHP', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'healthy' : 'critical', 'PHP '.PHP_VERSION.' is active.'),
            new HealthCheck('opcache', 'OPcache', $opcacheEnabled ? 'healthy' : 'warning', $opcacheEnabled ? 'PHP OPcache is enabled.' : 'PHP OPcache is not enabled. Enabling OPcache is strongly recommended for shared-host request performance.'),
            $this->database(),
            new HealthCheck('storage', 'Private storage', is_writable(storage_path('app/private')) ? 'healthy' : 'critical', is_writable(storage_path('app/private')) ? 'Private storage is writable.' : 'Private storage is not writable.'),
            $this->diskSpace(),
            $this->email(),
            $this->scheduler(),
            $this->backups(),
            new HealthCheck('https', 'HTTPS', $https ? 'healthy' : 'warning', $https ? 'The administration request is encrypted.' : 'HTTPS was not detected for this request.'),
            $this->updates(),
            $this->missingMedia(),
        ]);
    }

    private function applicationProtection(string $baseUrl): HealthCheck
    {
        $protected = $this->exposureProbe->protected($baseUrl);

        return match ($protected) {
            true => new HealthCheck('application-protection', 'Internal application protection', 'healthy', 'Internal application files are blocked from public requests.'),
            false => new HealthCheck('application-protection', 'Internal application protection', 'critical', 'Internal application files are publicly accessible. Correct the web-server protection immediately.'),
            null => new HealthCheck('application-protection', 'Internal application protection', 'warning', 'Internal application protection could not be confirmed.'),
        };
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
        $configured = $this->emailStatus->configured();

        return new HealthCheck('email', 'Email', $configured ? 'healthy' : 'warning', $configured ? 'Outbound email delivery is configured.' : 'Email delivery is not configured.');
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
        $configured = trim((string) config('waymark.updates.metadata_url')) !== ''
            && trim((string) config('waymark.updates.public_key_base64')) !== '';
        if (! $configured) {
            return new HealthCheck('updates', 'Updates', 'warning', 'A signed stable release feed is not configured.');
        }
        $state = $this->updateState->read();
        if ($state === null) {
            return new HealthCheck('updates', 'Updates', 'warning', 'No signed stable release check has completed yet.');
        }
        if (($state['status'] ?? null) === 'failed') {
            return new HealthCheck('updates', 'Updates', 'warning', 'The latest signed stable release check failed; no update information was accepted.');
        }
        if (($state['status'] ?? null) === 'update_failed') {
            $complete = ($state['rollback_complete'] ?? false) === true;

            return new HealthCheck('updates', 'Updates', $complete ? 'warning' : 'critical', (string) ($state['message'] ?? 'The latest update attempt failed.'));
        }
        if (($state['status'] ?? null) === 'installed') {
            return new HealthCheck('updates', 'Updates', 'healthy', (string) ($state['message'] ?? 'The verified stable release was installed successfully.'));
        }
        if (($state['update_available'] ?? false) === true) {
            $security = ($state['metadata']['security_release'] ?? false) === true;
            $compatible = ($state['compatibility']['compatible'] ?? false) === true;

            return new HealthCheck(
                'updates',
                'Updates',
                $security ? 'critical' : 'warning',
                ($security ? 'A verified security release' : 'A verified stable release').' is available'.($compatible ? '.' : ', but this host has compatibility blockers.'),
            );
        }

        return new HealthCheck('updates', 'Updates', 'healthy', 'No newer verified stable release is available.');
    }

    private function missingMedia(): HealthCheck
    {
        $count = MissingMediaRepair::query()->where('status', 'queued')->count();

        return new HealthCheck('missing-media', 'Missing media', $count > 0 ? 'critical' : 'healthy', $count > 0 ? $count.' missing file(s) need repair.' : 'No missing files are queued for repair.');
    }
}
