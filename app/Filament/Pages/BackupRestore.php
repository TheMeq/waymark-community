<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Backups\Actions\BeginGuidedRestore;
use App\Domain\Operations\Backups\Actions\ContinueGuidedRestore;
use App\Domain\Operations\Backups\GuidedRestoreStateStore;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use Throwable;

final class BackupRestore extends Page
{
    protected static ?string $title = 'Restore a backup';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Restore backup';

    protected static ?int $navigationSort = 20;

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.backup-restore';

    public ?int $backupId = null;

    public string $backupPassphrase = '';

    public string $restoreConfirmation = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'backup-restore';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    /** @return list<BackupRun> */
    public function backups(): array
    {
        return BackupRun::query()->where('status', 'completed')->latest('completed_at')->limit(50)->get()->all();
    }

    public function restore(): RedirectResponse|Redirector|null
    {
        try {
            $backup = BackupRun::query()->where('status', 'completed')->findOrFail($this->backupId);
            app(BeginGuidedRestore::class)->handle(
                $backup,
                $this->restoreConfirmation,
                trim($this->backupPassphrase) === '' ? null : $this->backupPassphrase,
            );

            Notification::make()->title('Safety backup started — continue when it completes')->success()->send();
            $this->backupPassphrase = '';
            $this->restoreConfirmation = '';

            return null;
        } catch (Throwable $exception) {
            report($exception);
            $this->backupPassphrase = '';
            $this->restoreConfirmation = '';
            Notification::make()->title('Restore refused — verify the backup, passphrase and exact confirmation')->danger()->send();

            return null;
        }
    }

    public function continueRestore(): RedirectResponse|Redirector|null
    {
        try {
            if (app(ContinueGuidedRestore::class)->handle()) {
                return to_route('filament.admin.pages.system-health');
            }
            Notification::make()->title('Safety backup advanced — more work remains')->success()->send();

            return null;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Restore remains paused — review or retry its safety backup')->danger()->send();

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function restoreState(): ?array
    {
        return app(GuidedRestoreStateStore::class)->read();
    }
}
