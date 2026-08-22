<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\SiteMedia\Actions\UpdateSiteMediaMetadata;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Filament\Pages\SiteMediaLibrary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SiteMediaCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_media_defaults_are_separate_from_global_photo_moderation_at_page_and_action_boundaries(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $media = SiteMedia::query()->create($this->attributes($administrator));

        $this->assertTrue($administrator->hasCapability(ModuleCapability::ManageSiteMedia));
        $this->assertTrue($moderator->hasCapability(ModuleCapability::ModerateAllCommunityPhotos));
        $this->assertFalse($moderator->hasCapability(ModuleCapability::ManageSiteMedia));

        $this->actingAs($administrator);
        $this->assertTrue(SiteMediaLibrary::canAccess());
        $this->get(SiteMediaLibrary::getUrl())->assertOk();
        app(UpdateSiteMediaMetadata::class)->handle($administrator, $media, new SiteMediaMetadata('Administrator managed media', false));
        $this->assertSame('Administrator managed media', $media->fresh()->alt_text);

        $this->actingAs($moderator);
        $this->assertFalse(SiteMediaLibrary::canAccess());
        $this->get(SiteMediaLibrary::getUrl())->assertForbidden();

        $this->expectException(AuthorizationException::class);
        app(UpdateSiteMediaMetadata::class)->handle($moderator, $media, new SiteMediaMetadata('Changed without authority', false));
    }

    public function test_a_non_administrator_explicitly_granted_site_media_management_can_use_page_and_action_boundaries(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $media = SiteMedia::query()->create($this->attributes($administrator));
        DB::table('role_capabilities')->insert([
            'role' => AccountRole::Moderator->value,
            'capability' => ModuleCapability::ManageSiteMedia->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($moderator);
        $this->assertTrue($moderator->hasCapability(ModuleCapability::ManageSiteMedia));
        $this->assertTrue(SiteMediaLibrary::canAccess());
        $this->get(SiteMediaLibrary::getUrl())->assertOk();

        app(UpdateSiteMediaMetadata::class)->handle($moderator, $media, new SiteMediaMetadata('Updated by configured manager', false, 0.25, 0.75));

        $this->assertDatabaseHas('site_media', ['id' => $media->id, 'alt_text' => 'Updated by configured manager', 'focal_point_x' => 0.25, 'focal_point_y' => 0.75]);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $media->id, 'actor_user_id' => $moderator->id, 'action' => 'metadata_updated']);
    }

    /** @return array<string, mixed> */
    private function attributes(User $creator): array
    {
        return [
            'created_by_user_id' => $creator->id,
            'storage_disk' => 'local',
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 1000,
            'file_size_bytes' => 1234,
            'alt_text' => 'Walkers on a ridge',
            'is_decorative' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
        ];
    }
}
