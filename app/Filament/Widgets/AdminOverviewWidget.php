<?php

namespace App\Filament\Widgets;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Support\OutboundEmailStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Domain\Membership\Queries\MembershipReviewsDue;
use App\Domain\Operations\Health\AdminHealthAlert;
use App\Filament\Pages\EmailDeliverySettings;
use App\Filament\Pages\MembershipVerification;
use App\Filament\Pages\PhotoModeration;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\HolidayResource;
use App\Filament\Resources\SocialResource;
use App\Filament\Resources\WalkResource;
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
            ->with(['walk', 'social', 'holiday'])
            ->currentOrUpcoming()
            ->when(! $canManageAllEvents, fn (Builder $events): Builder => $events->where('organiser_id', $actor->id))
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id', 'title', 'starts_at', 'type']);

        $eventLinks = [];
        foreach ($upcoming as $event) {
            [$resource, $record] = match ($event->type) {
                EventType::Walk => [WalkResource::class, $event->walk],
                EventType::Social => [SocialResource::class, $event->social],
                EventType::Holiday => [HolidayResource::class, $event->holiday],
            };
            if ($record && $resource::canEdit($record)) {
                $eventLinks[$event->id] = $resource::getUrl('edit', ['record' => $record]);
            }
        }

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
            'eventLinks' => $eventLinks,
            'photosUrl' => PhotoModeration::canAccess() ? PhotoModeration::getUrl() : null,
            'documentsUrl' => DocumentResource::canAccess() ? DocumentResource::getUrl() : null,
            'membershipUrl' => MembershipVerification::canAccess() ? MembershipVerification::getUrl() : null,
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
