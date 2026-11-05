<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class ProxiedMediaUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_branding_does_not_reuse_the_lan_origin_on_https_visits(): void
    {
        $media = $this->media();
        SiteProfile::query()->create(['id' => 1, 'group_name' => 'Trail Friends', 'logo_media_id' => $media->id]);
        URL::forceRootUrl('http://192.168.0.115:8080');
        URL::forceScheme('http');
        $branding = app(PublicBranding::class)->get();
        $this->assertSame('/media/'.$media->id.'/image/medium', $branding['logo_url']);
        URL::forceRootUrl('https://demo.example.test');
        URL::forceScheme('https');
        $this->assertSame($branding['logo_url'], app(PublicBranding::class)->get()['logo_url']);
    }

    public function test_managed_images_keep_the_application_subdirectory_without_an_origin(): void
    {
        $media = $this->media();
        config()->set('app.url', 'http://192.168.0.115:8080/demo');
        URL::forceRootUrl('http://192.168.0.115:8080/demo');
        $this->assertSame('/demo/media/'.$media->id.'/image/medium', app(SiteMediaPresenter::class)->present($media, 'medium')->url);
    }

    private function media(): SiteMedia
    {
        Storage::fake('local');
        $path = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/medium.png';
        Storage::disk('local')->put($path, 'image');

        return SiteMedia::query()->create([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300', 'storage_disk' => 'local',
            'processed_variants' => ['medium' => $path], 'mime_type' => 'image/png',
            'width' => 100, 'height' => 100, 'file_size_bytes' => 5,
            'is_decorative' => true, 'focal_point_x' => .5, 'focal_point_y' => .5,
            'processing_status' => 'complete', 'health_status' => 'healthy', 'purpose' => SiteMediaPurpose::SiteLogo,
        ]);
    }
}
