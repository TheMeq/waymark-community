<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Panel;

final class MaintenanceMode extends Page
{
    protected static ?string $title = 'Maintenance mode';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Maintenance mode';

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.maintenance-mode';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'maintenance-mode';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    public function active(): bool
    {
        return app(MaintenanceManager::class)->active();
    }
}
