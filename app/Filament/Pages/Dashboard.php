<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminOverviewWidget;
use App\Filament\Widgets\GettingStartedWidget;
use App\Filament\Widgets\QuickActionsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Home';

    public function getWidgets(): array
    {
        return [
            GettingStartedWidget::class,
            QuickActionsWidget::class,
            AdminOverviewWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
