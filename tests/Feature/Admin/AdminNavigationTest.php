<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AccountAdministration;
use App\Filament\Pages\BrandingSettings;
use App\Filament\Pages\EmailDeliverySettings;
use App\Filament\Pages\PhotoModeration;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\CmsPageResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\GradeResource;
use App\Filament\Resources\NewsArticleResource;
use App\Filament\Resources\SocialResource;
use App\Filament\Resources\WalkResource;
use Filament\Facades\Filament;
use Tests\TestCase;

final class AdminNavigationTest extends TestCase
{
    public function test_navigation_uses_task_groups_with_system_last(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(
            ['Events', 'Community', 'Content', 'People', 'Group settings', 'System'],
            array_values($panel->getNavigationGroups()),
        );
        $this->assertSame('Events', WalkResource::getNavigationGroup());
        $this->assertSame('Events', SocialResource::getNavigationGroup());
        $this->assertSame('Community', NewsArticleResource::getNavigationGroup());
        $this->assertSame('Community', PhotoModeration::getNavigationGroup());
        $this->assertSame('Content', CmsPageResource::getNavigationGroup());
        $this->assertSame('Content', DocumentResource::getNavigationGroup());
        $this->assertSame('People', AccountAdministration::getNavigationGroup());
        $this->assertSame('Group settings', BrandingSettings::getNavigationGroup());
        $this->assertSame('Group settings', GradeResource::getNavigationGroup());
        $this->assertSame('Group settings', EmailDeliverySettings::getNavigationGroup());
        $this->assertSame('System', SystemHealth::getNavigationGroup());
        $this->assertSame(10, WalkResource::getNavigationSort());
        $this->assertSame(10, PhotoModeration::getNavigationSort());
        $this->assertSame(10, CmsPageResource::getNavigationSort());
        $this->assertSame(10, AccountAdministration::getNavigationSort());
        $this->assertSame(10, BrandingSettings::getNavigationSort());
        $this->assertSame(10, SystemHealth::getNavigationSort());
    }
}
