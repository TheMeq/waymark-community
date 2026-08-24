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
        return app(HealthService::class)->report(request()->secure())->checks;
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
            app(CreateBackup::class)->handle('manual', $passphrase === '' ? null : $passphrase);
            app(PruneBackups::class)->handle();
            $this->backupPassphrase = '';
            Notification::make()->title('Backup completed')->success()->send();
        } catch (\Throwable) {
            $this->backupPassphrase = '';
            Notification::make()->title('Backup failed — review system health and try again')->danger()->send();
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
