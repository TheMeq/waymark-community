<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Updates\Actions\CheckForUpdates;
use App\Domain\Operations\Updates\UpdateStateStore;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Throwable;

final class UpdateCentre extends Page
{
    protected static ?string $title = 'Updates';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Updates';

    protected string $view = 'filament.pages.update-centre';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'update-centre';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    /** @return array<string, mixed>|null */
    public function state(): ?array
    {
        return app(UpdateStateStore::class)->read();
    }

    public function checkNow(): void
    {
        try {
            $result = app(CheckForUpdates::class)->handle('manual');
            Notification::make()->title($result->updateAvailable ? 'A verified stable release is available' : 'Waymark is up to date')->success()->send();
        } catch (Throwable) {
            Notification::make()->title('Release check failed — no update information was accepted')->danger()->send();
        }
    }
}
