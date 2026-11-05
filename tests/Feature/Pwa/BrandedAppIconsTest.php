<?php

namespace Tests\Feature\Pwa;

use App\Domain\Operations\Actions\UpdateBrandingImage;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Actions\ResolvePublicImageHost;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BrandedAppIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_group_logo_is_used_for_square_installation_icons(): void
    {
        Storage::fake('local');
        $profile = SiteProfile::query()->create(['id' => 1, 'group_name' => 'Trail Friends']);
        $profile = app(UpdateBrandingImage::class)->handle(
            User::factory()->initialAdministrator()->create(), $profile, SiteMediaPurpose::SiteLogo,
            BrandingImageInput::from(['logo_source' => 'managed', 'logo_upload' => UploadedFile::fake()->image('wide-logo.png', 600, 200)], SiteMediaPurpose::SiteLogo),
        );
        $manifest = $this->get('/manifest.webmanifest')->assertOk();
        foreach ([192, 512] as $index => $size) {
            $url = $manifest->json('icons.'.$index.'.src');
            $this->assertStringContainsString('/app-icons/'.$size.'.png', $url);
            $this->assertStringContainsString('logo='.$profile->logo_media_id, $url);
            $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
            $dimensions = getimagesizefromstring($response->getContent());
            $this->assertSame([$size, $size], array_slice($dimensions, 0, 2));
            $image = imagecreatefromstring($response->getContent());
            $this->assertSame(127, imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha']);
            $this->assertSame(0, imagecolorsforindex($image, imagecolorat($image, (int) ($size / 2), (int) ($size / 2)))['alpha']);
            imagedestroy($image);
        }
        $this->get('/app-icons/999.png')->assertNotFound();
    }

    public function test_icon_endpoint_has_a_safe_fallback_without_a_logo(): void
    {
        $this->get('/app-icons/192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('icons.0.src', '/images/pwa/icon-192.png');
        $this->get('/')->assertOk()->assertSee('<link rel="apple-touch-icon" sizes="192x192" href="/app-icons/192.png">', false);
    }

    public function test_external_group_logo_is_selected_for_installation_icons(): void
    {
        $this->app->instance(ResolvePublicImageHost::class, new class extends ResolvePublicImageHost
        {
            public function resolve(string $host): ?string
            {
                return '93.184.216.34';
            }
        });
        Http::fake(['https://images.example.test/group.png' => Http::response(UploadedFile::fake()->image('group.png', 102, 92)->getContent(), 200, ['Content-Type' => 'image/png'])]);
        SiteProfile::query()->create(['id' => 1, 'group_name' => 'Trail Friends', 'logo_path' => 'https://images.example.test/group.png']);
        $manifest = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringStartsWith('/app-icons/192.png?logo=', $manifest->json('icons.0.src'));
        $this->assertStringStartsWith('/app-icons/512.png?logo=', $manifest->json('icons.1.src'));
        foreach ([192, 512] as $index => $size) {
            $response = $this->get($manifest->json('icons.'.$index.'.src'))->assertOk()->assertHeader('Content-Type', 'image/png');
            $dimensions = getimagesizefromstring($response->getContent());
            $this->assertSame([$size, $size], array_slice($dimensions, 0, 2));
        }
        Http::assertSentCount(1);
    }
}
