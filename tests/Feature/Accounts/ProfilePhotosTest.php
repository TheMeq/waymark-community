<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\RequestPersonalDataExport;
use App\Domain\Accounts\Actions\UpdateProfilePhoto;
use App\Domain\Accounts\Data\ProfilePhotoInput;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\ProfilePhoto;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ProfilePhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_verified_member_uploads_a_photo_with_saved_preview_and_immediate_public_stream(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertRedirect(route('account.profile.edit'))->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $this->assertSame($member->id, $photo->user_id);
        $this->assertSame(['medium', 'thumbnail'], array_keys($photo->processed_variants));
        foreach ($photo->processed_variants as $path) {
            $this->assertStringStartsWith('profile-photos/', $path);
        }
        $url = route('profile-photos.image', [$photo, 'thumbnail']);
        $this->actingAs($member)->get(route('account.profile.edit'))->assertOk()->assertSee($url, false)->assertSee('Save photo');
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('Content-Type', $photo->mime_type)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseCount('community_photos', 0);
        $this->assertDatabaseCount('site_media', 0);
    }

    public function test_replacement_and_removal_delete_old_files_and_invalidate_old_urls(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $old = ProfilePhoto::query()->sole();
        $this->upload($member)->assertSessionHasNoErrors();
        $new = ProfilePhoto::query()->sole();
        $this->assertNotSame($old->id, $new->id);
        foreach ($old->processed_variants as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->get(route('profile-photos.image', [$old, 'thumbnail']))->assertNotFound();
        $this->actingAs($member)->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('profile_photos', 0);
        foreach ($new->processed_variants as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_external_photo_and_no_image_follow_the_existing_image_source_workflow(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('account.profile.photo.update'), [
            'profile_photo_source' => 'external', 'profile_photo_url' => 'https://example.org/member.png',
        ])->assertSessionHasNoErrors();
        $this->assertSame('https://example.org/member.png', $member->fresh()->profile_photo_reference);
        $this->get(route('account.profile.edit'))->assertSee('https://example.org/member.png', false);
        $this->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->profile_photo_reference);
    }

    public function test_invalid_uploads_and_unsafe_external_urls_preserve_the_saved_photo(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $id = ProfilePhoto::query()->sole()->id;
        foreach ([UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('broken.jpg', 1, 'image/jpeg'), UploadedFile::fake()->create('large.jpg', 11000, 'image/jpeg')] as $upload) {
            $this->actingAs($member)->post(route('account.profile.photo.update'), ['profile_photo_source' => 'managed', 'profile_photo_upload' => $upload])->assertSessionHasErrors('profile_photo_upload');
            $this->assertSame($id, ProfilePhoto::query()->sole()->id);
        }
        $this->post(route('account.profile.photo.update'), ['profile_photo_source' => 'external', 'profile_photo_url' => 'javascript:alert(1)'])->assertSessionHasErrors('profile_photo_url');
        $this->assertSame($id, ProfilePhoto::query()->sole()->id);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_switching_to_external_removes_the_upload_without_resurrecting_a_fallback(): void
    {
        $member = User::factory()->create(['profile_photo_reference' => 'https://example.org/old.png']);
        $this->upload($member)->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $this->assertNull($member->fresh()->profile_photo_reference);
        $this->post(route('account.profile.photo.update'), [
            'profile_photo_source' => 'external', 'profile_photo_url' => 'https://example.org/new.png',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('profile_photos', 0);
        foreach ($photo->processed_variants as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertSame('https://example.org/new.png', $member->fresh()->profile_photo_reference);
    }

    public function test_database_failure_preserves_the_old_photo_and_cleans_up_the_new_files(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $old = ProfilePhoto::query()->sole();
        $event = 'eloquent.creating: '.ProfilePhoto::class;
        Event::listen($event, function (): void {
            throw new \RuntimeException('Simulated database failure');
        });
        try {
            app(UpdateProfilePhoto::class)->handle($member,
                ProfilePhotoInput::from([
                    'profile_photo_source' => 'managed',
                    'profile_photo_upload' => UploadedFile::fake()->image('replacement.jpg', 640, 480),
                ]));
            $this->fail('The simulated database failure must escape the action.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame($old->id, ProfilePhoto::query()->sole()->id);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_external_urls_longer_than_the_persisted_limit_are_rejected(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('account.profile.photo.update'), [
            'profile_photo_source' => 'external', 'profile_photo_url' => 'https://example.org/'.str_repeat('a', 250).'.png',
        ])->assertSessionHasErrors('profile_photo_url');
        $this->assertNull($member->fresh()->profile_photo_reference);
    }

    public function test_failed_file_deletions_remain_private_and_are_retried_by_bounded_cleanup(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $disk = Storage::disk('local');
        $proxy = \Mockery::mock($disk);
        $attempt = 0;
        $proxy->shouldReceive('delete')->andReturnUsing(function ($paths) use (&$attempt, $disk): bool {
            return $attempt++ === 0 ? false : $disk->delete($paths);
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $this->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertSessionHasNoErrors();
        $this->assertNull(ProfilePhoto::query()->sole()->user_id);
        $this->get(route('profile-photos.image', [$photo, 'thumbnail']))->assertNotFound();
        $this->artisan('accounts:cleanup-profile-photos')->assertExitCode(0);
        $this->assertDatabaseCount('profile_photos', 0);
        foreach ($photo->processed_variants as $path) {
            $disk->assertMissing($path);
        }
    }

    public function test_managed_source_keeps_an_existing_upload_and_requires_a_file_when_empty(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('account.profile.photo.update'), ['profile_photo_source' => 'managed'])->assertSessionHasErrors('profile_photo_upload');
        $this->upload($member)->assertSessionHasNoErrors();
        $id = ProfilePhoto::query()->sole()->id;
        $this->post(route('account.profile.photo.update'), ['profile_photo_source' => 'managed'])->assertSessionHasNoErrors();
        $this->assertSame($id, ProfilePhoto::query()->sole()->id);
    }

    public function test_updates_require_a_verified_active_owner_and_ignore_supplied_member_ids(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $this->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertRedirect(route('login'));
        $this->actingAs($member)->post(route('account.profile.photo.update'), [
            'profile_photo_source' => 'external', 'profile_photo_url' => 'https://example.org/self.png', 'user_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $this->assertNull($other->fresh()->profile_photo_reference);
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertForbidden();
        $disabled = User::factory()->create(['account_status' => AccountStatus::Disabled]);
        $this->actingAs($disabled)->post(route('account.profile.photo.update'), ['profile_photo_source' => 'none'])->assertRedirect(route('login'));
    }

    public function test_public_leader_profile_uses_the_members_uploaded_photo(): void
    {
        $member = User::factory()->create(['role' => AccountRole::WalkLeader, 'public_profile_enabled' => true, 'public_profile_slug' => 'test-leader']);
        $this->upload($member)->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $this->get(route('leaders.show', 'test-leader'))->assertOk()->assertSee(route('profile-photos.image', [$photo, 'medium']), false);
        Storage::disk('local')->delete($photo->processed_variants['thumbnail']);
        $this->get(route('profile-photos.image', [$photo, 'thumbnail']))->assertNotFound();
        $this->get(route('profile-photos.image', [$photo, 'original']))->assertNotFound();
    }

    public function test_personal_data_export_includes_the_public_photo_reference_without_private_file_paths(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $export = app(RequestPersonalDataExport::class)->handle($member);
        app(ProcessPersonalDataExports::class)->handle(1);
        $payload = json_decode(Storage::disk('local')->get($export->fresh()->storage_path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(route('profile-photos.image', [$photo, 'medium']), $payload['profile']['photo_url']);
        $this->assertStringNotContainsString('community-photos/', json_encode($payload));
    }

    public function test_account_deletion_removes_profile_photos_and_their_files(): void
    {
        $member = User::factory()->create();
        $this->upload($member)->assertSessionHasNoErrors();
        $photo = ProfilePhoto::query()->sole();
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        app(ApproveAccountDeletion::class)->handle($administrator, app(RequestAccountDeletion::class)->handle($member));
        $this->assertDatabaseCount('profile_photos', 0);
        foreach ($photo->processed_variants as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    private function upload(User $member): TestResponse
    {
        return $this->actingAs($member)->post(route('account.profile.photo.update'), [
            'profile_photo_source' => 'managed', 'profile_photo_upload' => UploadedFile::fake()->image('member.jpg', 640, 480),
        ]);
    }
}
