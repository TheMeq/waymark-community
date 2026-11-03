<?php

namespace App\Filament\Widgets;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Support\OutboundEmailStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Domain\Membership\Queries\MembershipReviewsDue;
use App\Domain\Operations\Health\AdminHealthAlert;
use App\Filament\Pages\EmailDeliverySettings;
use App\Filament\Pages\SystemHealth;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

final class AdminOverviewWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.admin-overview';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $actor */
        $actor = auth()->user();
        $canManageAllEvents = $actor->hasCapability(ModuleCapability::ManageAllWalks)
            || $actor->hasCapability(ModuleCapability::ManageSocials)
            || $actor->hasCapability(ModuleCapability::ManageHolidays);
        $upcoming = Event::query()
            ->currentOrUpcoming()
            ->when(! $canManageAllEvents, fn (Builder $events): Builder => $events->where('organiser_id', $actor->id))
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id', 'title', 'starts_at', 'type']);

        $pendingPhotos = null;
        if ($actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            $pendingPhotos = CommunityPhoto::query()->where('moderation_status', 'pending')->count();
        } elseif ($actor->hasCapability(ModuleCapability::ModerateOwnEventPhotos)) {
            $pendingPhotos = CommunityPhoto::query()
                ->where('moderation_status', 'pending')
                ->whereHas('event', fn (Builder $events): Builder => $events->where('organiser_id', $actor->id))
                ->count();
        }

        $documentsDue = $actor->hasCapability(ModuleCapability::ManageGovernance)
            ? app(DocumentsDueForReview::class)->get()->count()
            : null;
        $membershipReviews = $actor->hasCapability(ModuleCapability::ManageMembershipVerification)
            ? app(MembershipReviewsDue::class)->handle()->count()
            : null;
        $emailConfigured = app(OutboundEmailStatus::class)->configured();

        return [
            'upcoming' => $upcoming,
            'pendingPhotos' => $pendingPhotos,
            'documentsDue' => $documentsDue,
            'membershipReviews' => $membershipReviews,
            'emailConfigured' => $emailConfigured,
            'emailUrl' => EmailDeliverySettings::canAccess() ? EmailDeliverySettings::getUrl() : null,
            'healthNeedsAttention' => SystemHealth::canAccess() && app(AdminHealthAlert::class)->serious(request()->secure()),
            'healthUrl' => SystemHealth::canAccess() ? SystemHealth::getUrl() : null,
        ];
    }
}
