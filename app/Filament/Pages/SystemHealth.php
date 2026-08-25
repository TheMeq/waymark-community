<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\PruneBackups;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Health\Actions\RecheckMissingMedia;
use App\Domain\Operations\Health\Actions\ScanMissingMedia;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\Operations\Health\SystemHealth as HealthService;
use App\Domain\Operations\Scheduling\Contracts\FallbackRunner;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;

final class SystemHealth extends Page
{
    protected static ?string $title = 'System health';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'System health';

    protected string $view = 'filament.pages.system-health';

    public string $backupPassphrase = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'system-health';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    public function healthChecks(): array
    {
        return app(HealthService::class)->report(request()->secure(), request()->root())->checks;
    }

    public function repairs(): array
    {
        return MissingMediaRepair::query()->where('status', 'queued')->oldest('detected_at')->limit(100)->get()->all();
    }

    public function backups(): array
    {
        return BackupRun::query()->latest('id')->limit(20)->get()->all();
    }

    public function createBackup(): void
    {
        try {
            $passphrase = trim($this->backupPassphrase);
            app(CreateBackup::class)->start('manual', $passphrase === '' ? null : $passphrase);
            $this->backupPassphrase = '';
            Notification::make()->title('Backup started — use Continue backup work if cron is unavailable')->success()->send();
        } catch (\Throwable) {
            $this->backupPassphrase = '';
            Notification::make()->title('Backup failed — review system health and try again')->danger()->send();
        }
    }

    public function continueBackup(int $backupId): void
    {
        try {
            $backup = BackupRun::query()->whereIn('status', ['queued', 'running'])->findOrFail($backupId);
            app(CreateBackup::class)->advance($backup);
            app(PruneBackups::class)->handle();
            Notification::make()->title($backup->fresh()->status === 'completed' ? 'Backup completed' : 'Backup work advanced')->success()->send();
        } catch (\Throwable) {
            Notification::make()->title('Backup step failed — the run can be retried')->danger()->send();
        }
    }

    public function retryBackup(int $backupId): void
    {
        try {
            app(CreateBackup::class)->retry(BackupRun::query()->findOrFail($backupId));
            Notification::make()->title('Backup queued for a clean retry')->success()->send();
        } catch (\Throwable) {
            Notification::make()->title('Backup could not be retried')->danger()->send();
        }
    }

    public function scanMedia(): void
    {
        $result = app(ScanMissingMedia::class)->handle();
        $notification = Notification::make()->title($result->missing.' missing file(s) found');
        $result->missing > 0 ? $notification->warning() : $notification->success();
        $notification->send();
    }

    public function runFallback(): void
    {
        $result = app(FallbackRunner::class)->handle('admin');
        Notification::make()->title($result->ran ? 'Bounded fallback work ran' : 'Fallback work was not needed')->success()->send();
    }

    public function recheck(int $repairId): void
    {
        $resolved = app(RecheckMissingMedia::class)->handle(MissingMediaRepair::query()->findOrFail($repairId));
        $notification = Notification::make()->title($resolved ? 'Missing file is available again' : 'File is still missing');
        $resolved ? $notification->success() : $notification->warning();
        $notification->send();
    }
}
