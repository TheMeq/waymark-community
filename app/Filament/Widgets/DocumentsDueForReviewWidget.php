<?php

namespace App\Filament\Widgets;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DocumentsDueForReviewWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasCapability(ModuleCapability::ManageGovernance);
    }

    protected function getStats(): array
    {
        $due = app(DocumentsDueForReview::class)->get();

        return [Stat::make('Documents due for review', $due->count())->description($due->where('review_date', '<', today())->count().' overdue')];
    }
}
