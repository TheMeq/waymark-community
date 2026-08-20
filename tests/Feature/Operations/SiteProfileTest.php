<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Actions\GetSiteProfile;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Exceptions\SiteProfileNotConfigured;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SiteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_profile_foundation_types_exist(): void
    {
        $this->assertTrue(class_exists(\App\Domain\Operations\Models\SiteProfile::class));
        $this->assertTrue(class_exists(\App\Domain\Operations\Actions\GetSiteProfile::class));
        $this->assertTrue(class_exists(\App\Domain\Operations\Actions\UpdateSiteProfile::class));
    }

    public function test_site_profile_schema_holds_installation_identity_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('site_profiles', [
            'group_name',
            'short_name',
            'contact_email',
            'timezone',
            'locale',
            'distance_unit',
            'ascent_unit',
            'start_year',
            'logo_path',
            'primary_colour',
            'accent_colour',
            'affiliation_name',
            'affiliation_url',
            'module_configuration',
            'is_active',
        ]));
    }

    public function test_missing_site_profile_has_an_explicit_pre_install_failure(): void
    {
        $this->expectException(SiteProfileNotConfigured::class);

        app(GetSiteProfile::class)->handle();
    }

    public function test_update_creates_the_single_active_installation_identity(): void
    {
        $profile = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Example Walking Group',
            'short_name' => 'EWG',
            'contact_email' => 'hello@example.org',
            'timezone' => 'Europe/London',
            'locale' => 'en',
            'distance_unit' => 'miles',
            'ascent_unit' => 'feet',
            'start_year' => 2010,
            'module_configuration' => ['walks' => true],
        ]);

        $this->assertTrue($profile->exists);
        $this->assertTrue($profile->is(app(GetSiteProfile::class)->handle()));
        $this->assertSame(1, SiteProfile::query()->where('is_active', true)->count());
    }

    public function test_update_changes_the_existing_profile_without_creating_a_tenant(): void
    {
        $first = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Original Group',
        ]);

        $updated = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Renamed Group',
            'id' => 999,
            'is_active' => false,
        ]);

        $this->assertSame($first->getKey(), $updated->getKey());
        $this->assertSame('Renamed Group', $updated->group_name);
        $this->assertTrue($updated->is_active);
        $this->assertSame(1, SiteProfile::query()->count());
    }
}
