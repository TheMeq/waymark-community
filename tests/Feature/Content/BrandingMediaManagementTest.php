<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Operations\Actions\UpdateBrandingImage;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Filament\Pages\BrandingSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

final class BrandingMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
    }

    public function test_content_manager_uploads_decorative_logo_and_favicon_without_media_library_access(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => 'https://images.example.org/retained-logo.png',
            'favicon_path' => '/images/demo/favicon.png',
        ]);

        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('logo.png', 80, 60)),
        );
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteFavicon,
            $this->input(SiteMediaPurpose::SiteFavicon, 'managed', $this->image('favicon.png', 64, 64)),
        );

        $logo = $profile->logoMedia;
        $favicon = $profile->faviconMedia;
        $this->assertNotNull($logo);
        $this->assertNotNull($favicon);
        $this->assertTrue($manager->hasCapability(ModuleCapability::ManageContent));
        $this->assertFalse($manager->hasCapability(ModuleCapability::ManageSiteMedia));
        $this->assertSame(SiteMediaPurpose::SiteLogo, $logo->purpose);
        $this->assertSame(SiteMediaPurpose::SiteFavicon, $favicon->purpose);
        $this->assertTrue($logo->is_decorative);
        $this->assertTrue($favicon->is_decorative);
        $this->assertNull($logo->alt_text);
        $this->assertNull($favicon->alt_text);
        $this->assertNull($logo->orphaned_at);
        $this->assertNull($favicon->orphaned_at);
        $this->assertSame('https://images.example.org/retained-logo.png', $profile->logo_path);
        $this->assertSame('/images/demo/favicon.png', $profile->favicon_path);
        $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($logo->processed_variants));
        $this->assertSame(['favicon'], array_keys($favicon->processed_variants));
        $this->assertSame('image/png', $favicon->mime_type);

        foreach ([[$logo, 'logo'], [$favicon, 'favicon']] as [$media, $slot]) {
            $uploaded = SiteMediaAudit::query()->where('site_media_id', $media->id)->where('action', 'uploaded')->sole();
            $attached = SiteMediaAudit::query()->where('site_media_id', $media->id)->where('action', 'attached')->sole();
            $this->assertSame($media->purpose->value, $uploaded->after['purpose']);
            $this->assertSame(['owner_type' => 'site_profile', 'owner_id' => SiteProfile::SINGLETON_ID, 'slot' => $slot], $attached->context);
            $this->assertStringNotContainsString('site-media/', json_encode($attached->context, JSON_THROW_ON_ERROR));
        }
    }

    public function test_unauthorised_inactive_and_wrong_purpose_requests_fail_before_any_mutation(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Trail Friends']);
        $ordinary = User::factory()->create();
        $inactive = $this->contentManager(AccountStatus::Suspended);

        foreach ([$ordinary, $inactive] as $actor) {
            try {
                app(UpdateBrandingImage::class)->handle(
                    $actor,
                    $profile,
                    SiteMediaPurpose::SiteLogo,
                    $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('blocked.png', 80, 60)),
                );
                $this->fail('An unauthorised account changed branding media.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('branding', $exception->errors());
                $this->assertDatabaseCount('site_media', 0);
                $this->assertNull($profile->fresh()->logo_media_id);
            }
        }

        try {
            BrandingImageInput::from(['logo_source' => 'none'], SiteMediaPurpose::WalkFeaturedImage);
            $this->fail('A non-branding purpose was accepted by branding input.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('branding', $exception->errors());
        }

        $manager = $this->contentManager();
        $logoInput = $this->input(SiteMediaPurpose::SiteLogo, 'none');

        try {
            app(UpdateBrandingImage::class)->handle($manager, $profile, SiteMediaPurpose::SiteFavicon, $logoInput);
            $this->fail('A mismatched branding purpose was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('branding', $exception->errors());
            $this->assertDatabaseCount('site_media', 0);
        }
    }

    public function test_source_transitions_preserve_or_clear_fallbacks_and_orphan_only_unused_media(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => '/images/demo/waymark-logo.svg',
        ]);
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('managed.png', 80, 60)),
        );
        $firstManaged = $profile->logoMedia;

        $external = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'external', external: 'https://images.example.org/new-logo.png'),
        );
        $this->assertNull($external->logo_media_id);
        $this->assertSame('https://images.example.org/new-logo.png', $external->logo_path);
        $this->assertNotNull($firstManaged->fresh()->orphaned_at);

        foreach (['detached', 'orphaned'] as $action) {
            $audit = SiteMediaAudit::query()->where('site_media_id', $firstManaged->id)->where('action', $action)->sole();
            $this->assertSame(['owner_type' => 'site_profile', 'owner_id' => SiteProfile::SINGLETON_ID, 'slot' => 'logo'], $audit->context);
            $this->assertStringNotContainsString('site-media/', json_encode($audit->context, JSON_THROW_ON_ERROR));
        }

        $managedAgain = app(UpdateBrandingImage::class)->handle(
            $manager,
            $external,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('replacement.png', 80, 60), 'https://images.example.org/new-logo.png'),
        );
        $this->assertSame('https://images.example.org/new-logo.png', $managedAgain->logo_path);

        $revealed = app(UpdateBrandingImage::class)->handle(
            $manager,
            $managedAgain,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'none'),
        );
        $this->assertNull($revealed->logo_media_id);
        $this->assertSame('https://images.example.org/new-logo.png', $revealed->logo_path);

        $managedForClear = app(UpdateBrandingImage::class)->handle(
            $manager,
            $revealed,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('clear.png', 80, 60), 'https://images.example.org/new-logo.png'),
        );
        $cleared = app(UpdateBrandingImage::class)->handle(
            $manager,
            $managedForClear,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'none', removeFallback: true),
        );
        $this->assertNull($cleared->logo_media_id);
        $this->assertNull($cleared->logo_path);
    }

    public function test_shared_usage_prevents_orphaning_and_external_removal_leaves_unrelated_media_untouched(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create(['group_name' => 'Trail Friends']);
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('shared.png', 80, 60)),
        );
        $shared = $profile->logoMedia;
        $profile->forceFill(['favicon_media_id' => $shared->id])->save();

        app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile->fresh(),
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'external', external: 'https://images.example.org/logo.png'),
        );

        $this->assertNull($shared->fresh()->orphaned_at);
        $this->assertSame('healthy', $shared->fresh()->health_status);
        $this->assertSame($shared->id, $profile->fresh()->favicon_media_id);
        $this->assertTrue(Storage::disk('local')->exists($shared->processed_variants['master']));

        $externalRemoved = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile->fresh(),
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'none'),
        );
        $this->assertNull($externalRemoved->logo_path);
        $this->assertSame('healthy', $shared->fresh()->health_status);
        $this->assertNull($shared->fresh()->orphaned_at);
    }

    public function test_managed_upload_preserves_an_unchanged_unsafe_legacy_fallback_without_activating_it(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => '/storage/private/legacy-logo.png',
        ]);

        $updated = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(
                SiteMediaPurpose::SiteLogo,
                'managed',
                $this->image('managed.png', 80, 60),
                '/storage/private/legacy-logo.png',
            ),
        );

        $this->assertSame('/storage/private/legacy-logo.png', $updated->logo_path);
        $this->assertSame(
            route('site-media.stream', [$updated->logoMedia, 'medium']),
            app(PublicBranding::class)->forProfile($updated)['logo_url'],
        );

        Storage::disk('local')->delete($updated->logoMedia->processed_variants['medium']);
        $this->assertNull(app(PublicBranding::class)->forProfile($updated->refresh()->load('logoMedia'))['logo_url']);
    }

    public function test_processing_and_owner_transaction_failures_leave_previous_branding_active(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => '/images/demo/waymark-logo.svg',
        ]);
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('original.png', 80, 60)),
        );
        $original = $profile->logoMedia;

        try {
            app(UpdateBrandingImage::class)->handle(
                $manager,
                $profile,
                SiteMediaPurpose::SiteLogo,
                $this->input(SiteMediaPurpose::SiteLogo, 'managed', UploadedFile::fake()->createWithContent('broken.png', 'not-an-image')),
            );
            $this->fail('Malformed replacement processing succeeded.');
        } catch (ValidationException) {
            $this->assertSame($original->id, $profile->fresh()->logo_media_id);
        }

        SiteProfile::saving(static function (SiteProfile $saving) use ($profile): void {
            if ($saving->id === $profile->id && $saving->isDirty('logo_media_id')) {
                throw new RuntimeException('Owner transaction failed.');
            }
        });

        try {
            app(UpdateBrandingImage::class)->handle(
                $manager,
                $profile->fresh(),
                SiteMediaPurpose::SiteLogo,
                $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('transaction.png', 80, 60)),
            );
            $this->fail('A failed owner transaction changed the active logo.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Owner transaction failed.', $exception->getMessage());
        } finally {
            SiteProfile::flushEventListeners();
            SiteProfile::clearBootedModels();
        }

        $this->assertSame($original->id, $profile->fresh()->logo_media_id);
        $this->assertSame('/images/demo/waymark-logo.svg', $profile->fresh()->logo_path);
        $this->assertSame(1, SiteMedia::query()->where('health_status', '!=', 'removed')->count());
        $discarded = SiteMedia::query()->whereKeyNot($original->id)->sole();
        $this->assertSame('removed', $discarded->health_status);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $discarded->id, 'action' => 'removal_requested']);
        $this->assertDatabaseHas('site_media_audits', ['site_media_id' => $discarded->id, 'action' => 'removed']);
    }

    public function test_public_resolution_is_managed_first_safe_fallback_second_prefix_aware_and_cache_invalidated(): void
    {
        URL::forceRootUrl('https://example.org/demo-site/ndwg');
        URL::forceScheme('https');
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Prefix Walkers',
            'logo_path' => '/images/demo/waymark-logo.svg',
            'favicon_path' => 'https://images.example.org/favicon.png',
        ]);
        $cached = app(PublicBranding::class)->get();
        $this->assertSame('/demo-site/ndwg/images/demo/waymark-logo.svg', $cached['logo_url']);
        $this->assertTrue(Cache::has(PublicContentCache::BRANDING));

        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteLogo,
            $this->input(SiteMediaPurpose::SiteLogo, 'managed', $this->image('logo.png', 80, 60)),
        );
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteFavicon,
            $this->input(SiteMediaPurpose::SiteFavicon, 'managed', $this->image('favicon.png', 64, 64)),
        );
        $this->assertFalse(Cache::has(PublicContentCache::BRANDING));

        $branding = app(PublicBranding::class)->forProfile($profile);
        $this->assertSame('https://example.org/demo-site/ndwg/media/'.$profile->logo_media_id.'/image/medium', $branding['logo_url']);
        $this->assertSame('https://example.org/demo-site/ndwg/media/'.$profile->favicon_media_id.'/image/favicon', $branding['favicon_url']);
        $this->assertSame('image/png', $branding['favicon_type']);

        Storage::disk('local')->delete($profile->logoMedia->processed_variants['medium']);
        Storage::disk('local')->delete($profile->faviconMedia->processed_variants['favicon']);
        $fallback = app(PublicBranding::class)->forProfile($profile->refresh()->load(['logoMedia', 'faviconMedia']));
        $this->assertSame('/demo-site/ndwg/images/demo/waymark-logo.svg', $fallback['logo_url']);
        $this->assertSame('https://images.example.org/favicon.png', $fallback['favicon_url']);
        $this->assertNull($fallback['favicon_type']);

        $profile->forceFill(['logo_path' => '/storage/private/logo.png', 'favicon_path' => 'javascript:alert(1)'])->save();
        $invalid = app(PublicBranding::class)->forProfile($profile->refresh()->load(['logoMedia', 'faviconMedia']));
        $this->assertNull($invalid['logo_url']);
        $this->assertNull($invalid['favicon_url']);

        $wrongPurpose = $profile->faviconMedia;
        $wrongVariants = $wrongPurpose->processed_variants;
        $wrongVariants['medium'] = 'site-media/'.$wrongPurpose->storage_key.'/medium.png';
        Storage::disk('local')->put($wrongVariants['medium'], 'safe image');
        $wrongPurpose->forceFill(['processed_variants' => $wrongVariants])->save();
        $profile->forceFill(['logo_media_id' => $wrongPurpose->id])->save();

        $mismatched = app(PublicBranding::class)->forProfile($profile->refresh()->load(['logoMedia', 'faviconMedia']));
        $this->assertNull($mismatched['logo_url']);
    }

    public function test_general_profile_update_cannot_mutate_branding_media_sources(): void
    {
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => '/images/demo/waymark-logo.svg',
            'favicon_path' => '/images/demo/favicon.png',
        ]);

        app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Updated Trail Friends',
            'logo_path' => 'https://images.example.org/ignored-logo.png',
            'favicon_path' => 'https://images.example.org/ignored-favicon.png',
            'logo_media_id' => 999,
            'favicon_media_id' => 999,
        ]);

        $updated = $profile->fresh();
        $this->assertSame('Updated Trail Friends', $updated->group_name);
        $this->assertSame('/images/demo/waymark-logo.svg', $updated->logo_path);
        $this->assertSame('/images/demo/favicon.png', $updated->favicon_path);
        $this->assertNull($updated->logo_media_id);
        $this->assertNull($updated->favicon_media_id);
    }

    public function test_branding_page_is_upload_first_and_hides_storage_implementation_details(): void
    {
        $manager = $this->contentManager();
        SiteProfile::query()->create(['group_name' => 'Trail Friends']);

        $this->actingAs($manager);
        $component = Livewire::test(BrandingSettings::class)
            ->assertFormSet([
                'logo_source' => 'managed',
                'favicon_source' => 'managed',
            ])
            ->assertFormFieldVisible('logo_source')
            ->assertFormFieldVisible('favicon_source')
            ->assertFormFieldVisible('logo_upload')
            ->assertFormFieldVisible('favicon_upload')
            ->assertFormFieldHidden('logo_path')
            ->assertFormFieldHidden('favicon_path')
            ->assertFormFieldDoesNotExist('logo_media_id')
            ->assertFormFieldDoesNotExist('favicon_media_id')
            ->assertFormFieldDoesNotExist('storage_disk')
            ->assertFormFieldDoesNotExist('storage_key')
            ->assertFormFieldExists('logo_upload', null, fn ($field): bool => ! $field->shouldStoreFiles()
                && ! $field->canEditSvgs()
                && ! in_array('image/svg+xml', $field->getAcceptedFileTypes() ?? [], true))
            ->assertFormFieldExists('favicon_upload', null, fn ($field): bool => ! $field->shouldStoreFiles()
                && ! $field->canEditSvgs()
                && ! in_array('image/x-icon', $field->getAcceptedFileTypes() ?? [], true))
            ->assertSee('Upload a local logo (recommended)')
            ->assertSee('Use an external logo instead')
            ->assertSee('Upload a local favicon (recommended)')
            ->assertSee('square image around 512 by 512 pixels')
            ->assertFormFieldDoesNotExist('logo_svg')
            ->assertFormFieldDoesNotExist('favicon_ico')
            ->assertFormFieldDoesNotExist('pwa_icon');

        $component
            ->fillForm([
                'logo_source' => 'managed',
                'favicon_source' => 'external',
            ])
            ->assertFormFieldVisible('logo_upload')
            ->assertFormFieldHidden('logo_path')
            ->assertFormFieldHidden('favicon_upload')
            ->assertFormFieldVisible('favicon_path');
    }

    public function test_branding_page_saves_managed_logo_and_favicon_and_reloads_safe_previews(): void
    {
        $manager = $this->contentManager();
        SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'logo_path' => 'https://images.example.org/retained-logo.png',
            'favicon_path' => '/images/demo/favicon.png',
            'typography_option' => 'instrument',
        ]);

        $this->actingAs($manager);
        Livewire::test(BrandingSettings::class)
            ->fillForm([
                'group_name' => 'Managed Trail Friends',
                'logo_source' => 'managed',
                'logo_upload' => $this->image('transparent-logo.png', 80, 60),
                'favicon_source' => 'managed',
                'favicon_upload' => $this->image('favicon.png', 64, 64),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile = SiteProfile::query()->with(['logoMedia', 'faviconMedia'])->findOrFail(SiteProfile::SINGLETON_ID);
        $this->assertSame('Managed Trail Friends', $profile->group_name);
        $this->assertSame(SiteMediaPurpose::SiteLogo, $profile->logoMedia?->purpose);
        $this->assertSame(SiteMediaPurpose::SiteFavicon, $profile->faviconMedia?->purpose);
        $this->assertSame('https://images.example.org/retained-logo.png', $profile->logo_path);
        $this->assertSame('/images/demo/favicon.png', $profile->favicon_path);

        $component = Livewire::test(BrandingSettings::class)
            ->assertFormSet([
                'logo_source' => 'managed',
                'favicon_source' => 'managed',
            ])
            ->assertSee('Saved local logo.')
            ->assertSee('Saved local favicon.')
            ->assertSee('saved external fallback is retained');

        $component
            ->fillForm(['logo_source' => 'none'])
            ->assertFormFieldVisible('logo_remove_fallback')
            ->assertSee('Also remove the saved external fallback');

        $logoPreview = $component->get('logoPreview');
        $faviconPreview = $component->get('faviconPreview');
        $this->assertSame(route('site-media.stream', [$profile->logoMedia, 'medium']), $logoPreview['url']);
        $this->assertSame(route('site-media.stream', [$profile->faviconMedia, 'favicon']), $faviconPreview['url']);
        $this->assertStringNotContainsString((string) $profile->logoMedia->processed_variants['medium'], $component->html());
        $this->assertStringNotContainsString((string) $profile->faviconMedia->processed_variants['favicon'], $component->html());
    }

    public function test_branding_page_saves_ordinary_settings_without_requiring_new_branding_images(): void
    {
        $manager = $this->contentManager();
        SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'typography_option' => 'instrument',
        ]);

        $this->actingAs($manager);
        Livewire::test(BrandingSettings::class)
            ->fillForm(['group_name' => 'Updated Trail Friends'])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile = SiteProfile::query()->findOrFail(SiteProfile::SINGLETON_ID);
        $this->assertSame('Updated Trail Friends', $profile->group_name);
        $this->assertNull($profile->logo_media_id);
        $this->assertNull($profile->favicon_media_id);
    }

    public function test_branding_page_maps_favicon_processing_errors_and_preserves_the_saved_preview(): void
    {
        $manager = $this->contentManager();
        $profile = SiteProfile::query()->create([
            'group_name' => 'Trail Friends',
            'typography_option' => 'instrument',
        ]);
        $profile = app(UpdateBrandingImage::class)->handle(
            $manager,
            $profile,
            SiteMediaPurpose::SiteFavicon,
            $this->input(SiteMediaPurpose::SiteFavicon, 'managed', $this->image('saved-favicon.png', 64, 64)),
        );
        $savedFaviconId = $profile->favicon_media_id;

        $this->actingAs($manager);
        $component = Livewire::test(BrandingSettings::class);
        $savedPreviewUrl = $component->get('faviconPreview')['url'];
        $component->call('save')->assertSet('saveStatus', 'Branding saved.');

        $component
            ->fillForm([
                'favicon_source' => 'managed',
                'favicon_upload' => $this->image('not-square.png', 80, 64),
            ])
            ->call('save')
            ->assertHasFormErrors(['favicon_upload'])
            ->assertSet('saveStatus', '')
            ->assertSee('must be square');

        $this->assertSame($savedFaviconId, $profile->fresh()->favicon_media_id);
        $this->assertSame($savedPreviewUrl, $component->get('faviconPreview')['url']);
        $this->assertNotNull($component->get('data.favicon_upload'));
    }

    private function contentManager(AccountStatus $status = AccountStatus::Active): User
    {
        RoleCapability::query()->updateOrCreate([
            'role' => AccountRole::Moderator,
            'capability' => ModuleCapability::ManageContent,
        ]);

        return User::factory()->create([
            'role' => AccountRole::Moderator,
            'account_status' => $status,
        ]);
    }

    private function input(
        SiteMediaPurpose $purpose,
        string $source,
        ?UploadedFile $upload = null,
        ?string $external = null,
        bool $removeFallback = false,
    ): BrandingImageInput {
        $slot = $purpose === SiteMediaPurpose::SiteLogo ? 'logo' : 'favicon';

        return BrandingImageInput::from([
            $slot.'_source' => $source,
            $slot.'_upload' => $upload,
            $slot.'_path' => $external,
            $slot.'_remove_fallback' => $removeFallback,
        ], $purpose);
    }

    private function image(string $name, int $width, int $height): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }
}
