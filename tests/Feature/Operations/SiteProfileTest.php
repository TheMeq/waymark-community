<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Actions\GetSiteProfile;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Exceptions\SiteProfileNotConfigured;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SiteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_profile_foundation_types_exist(): void
    {
        $this->assertTrue(class_exists(SiteProfile::class));
        $this->assertTrue(class_exists(GetSiteProfile::class));
        $this->assertTrue(class_exists(UpdateSiteProfile::class));
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
            'singleton_key',
        ]));
        $this->assertFalse(Schema::hasColumn('site_profiles', 'is_active'));
    }

    public function test_missing_site_profile_has_an_explicit_pre_install_failure(): void
    {
        $this->expectException(SiteProfileNotConfigured::class);

        app(GetSiteProfile::class)->handle();
    }

    public function test_first_update_creates_the_canonical_installation_identity(): void
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
        $this->assertSame(SiteProfile::SINGLETON_ID, $profile->getKey());
        $this->assertTrue($profile->is(app(GetSiteProfile::class)->handle()));
        $this->assertSame(1, SiteProfile::query()->count());
    }

    public function test_update_changes_the_existing_profile_without_creating_a_tenant(): void
    {
        $first = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Original Group',
        ]);

        $updated = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Renamed Group',
            'id' => 999,
        ]);

        $this->assertSame($first->getKey(), $updated->getKey());
        $this->assertSame('Renamed Group', $updated->group_name);
        $this->assertSame(1, SiteProfile::query()->count());
    }

    public function test_database_rejects_an_additional_installation_identity(): void
    {
        $profile = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Canonical Group',
        ]);

        try {
            DB::table('site_profiles')->insert([
                'id' => 2,
                'group_name' => 'Competing Group',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('The database accepted a second installation identity.');
        } catch (QueryException) {
            $this->assertSame(1, SiteProfile::query()->count());
            $this->assertTrue($profile->is(app(GetSiteProfile::class)->handle()));
        }
    }
}
