<?php

namespace App\Filament\Widgets;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Membership\Queries\MembershipReviewsDue;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

final class MembershipReviewsDueWidget extends Widget
{
    protected string $view = 'filament.widgets.membership-reviews-due';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageMembershipVerification);
    }

    /** @return array{accounts: Collection<int, User>} */
    protected function getViewData(): array
    {
        return ['accounts' => app(MembershipReviewsDue::class)->handle()];
    }
}
