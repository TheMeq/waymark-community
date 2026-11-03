<?php

namespace App\Filament\Widgets;

use App\Domain\Accounts\Queries\EligibleWalkLeadersQuery;
use App\Domain\Communication\Support\OutboundEmailStatus;
use App\Domain\Governance\Models\Document;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Filament\Pages\AccountAdministration;
use App\Filament\Pages\BrandingSettings;
use App\Filament\Pages\EmailDeliverySettings;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\GradeResource;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Widgets\Widget;

final class GettingStartedWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.getting-started';

    protected int|string|array $columnSpan = 'full';

    public bool $visible = true;

    public static function canView(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->admin_onboarding_dismissed_at === null;
    }

    public function dismiss(): void
    {
        $actor = auth()->user();
        if ($actor instanceof User) {
            $actor->forceFill(['admin_onboarding_dismissed_at' => now()])->save();
        }

        $this->visible = false;
    }

    /** @return array{tasks:list<array{label:string,complete:bool,url:?string}>,emailConfigured:bool,emailUrl:?string} */
    protected function getViewData(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $emailConfigured = app(OutboundEmailStatus::class)->configured();

        return [
            'emailConfigured' => $emailConfigured,
            'emailUrl' => EmailDeliverySettings::canAccess() ? EmailDeliverySettings::getUrl() : null,
            'tasks' => [
                ['label' => 'Review group branding', 'complete' => filled($profile->group_name) && (filled($profile->logo_path) || filled($profile->hero_default_path)), 'url' => BrandingSettings::canAccess() ? BrandingSettings::getUrl() : null],
                ['label' => 'Add your first walk', 'complete' => Walk::query()->exists(), 'url' => WalkResource::canCreate() ? WalkResource::getUrl('create') : null],
                ['label' => 'Configure email delivery', 'complete' => $emailConfigured, 'url' => EmailDeliverySettings::canAccess() ? EmailDeliverySettings::getUrl() : null],
                ['label' => 'Review grading', 'complete' => Grade::query()->exists(), 'url' => GradeResource::canAccess() ? GradeResource::getUrl() : null],
                ['label' => 'Add key documents and policies', 'complete' => Document::query()->exists(), 'url' => DocumentResource::canCreate() ? DocumentResource::getUrl('create') : null],
                ['label' => 'Invite or create additional organisers', 'complete' => app(EligibleWalkLeadersQuery::class)->builder()->count() > 1, 'url' => AccountAdministration::canAccess() ? AccountAdministration::getUrl() : null],
            ],
        ];
    }
}
