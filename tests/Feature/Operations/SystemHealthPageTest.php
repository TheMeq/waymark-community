<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\Operations\Health\OpcodeCacheProbe;
use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class SystemHealthPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_health_page_uses_plain_statuses_without_a_raw_log_viewer(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin/system-health')
            ->assertSuccessful()
            ->assertSee('System health')
            ->assertSee('Platform')
            ->assertSee('PHP')
            ->assertSee('Database')
            ->assertSee('Private storage')
            ->assertSee('Disk space')
            ->assertSee('Email')
            ->assertSee('Scheduler')
            ->assertSee('Backups')
            ->assertSee('HTTPS')
            ->assertSee('Updates')
            ->assertSee('Missing media')
            ->assertSee('Create backup')
            ->assertDontSee('laravel.log')
            ->assertDontSee('Stack trace');
    }

    public function test_health_page_requires_administration_access(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/system-health')
            ->assertForbidden();
    }

    public function test_system_health_warns_when_outbound_email_was_deliberately_left_unconfigured(): void
    {
        config()->set('waymark.email.configured', false);
        config()->set('mail.default', 'array');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin/system-health')
            ->assertSuccessful()
            ->assertSee('Email delivery is not configured.')
            ->assertSee('Configure email delivery');
    }

    public function test_system_health_strongly_recommends_opcache_when_it_is_unavailable(): void
    {
        $probe = Mockery::mock(OpcodeCacheProbe::class);
        $probe->shouldReceive('enabled')->andReturnFalse();
        $this->instance(OpcodeCacheProbe::class, $probe);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin/system-health')
            ->assertSuccessful()
            ->assertSee('OPcache')
            ->assertSee('strongly recommended');
    }

    public function test_ordinary_admin_pages_do_not_run_the_public_application_exposure_probe(): void
    {
        config()->set('waymark.deployment_layout', 'public-html');
        Cache::clear();
        $probe = Mockery::mock(PublicApplicationExposureProbe::class);
        $probe->shouldNotReceive('protected');
        $this->instance(PublicApplicationExposureProbe::class, $probe);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_serious_missing_media_creates_an_admin_banner_with_repair_link(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        MissingMediaRepair::query()->create([
            'reference_hash' => MissingMediaRepair::referenceHash('test-media', 1, 'local', 'site-media/test/missing.jpg'),
            'media_type' => 'test-media',
            'record_id' => 1,
            'storage_disk' => 'local',
            'path' => 'site-media/test/missing.jpg',
            'status' => 'queued',
            'detected_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('System health needs attention')
            ->assertSee('/admin/system-health', false);
    }
}
