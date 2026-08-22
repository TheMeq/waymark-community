<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\DeletePendingCommunityPhoto;
use App\Domain\Gallery\Actions\ProcessDeferredCommunityPhotos;
use App\Domain\Gallery\Actions\RequestCommunityPhotoRemoval;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoRemovalRequest;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoReport;
use App\Domain\Gallery\Actions\SubmitCommunityPhotoReport;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoProcessingJob;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoReports;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CommunityPhotoReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_an_anonymous_visitor_can_report_a_safely_presented_published_photo_with_each_fixed_reason(): void
    {
        $photo = $this->publishedPhoto();

        foreach (['in_photo', 'privacy', 'copyright', 'inappropriate', 'other'] as $reason) {
            app(SubmitCommunityPhotoReport::class)->handle($photo, $reason, $reason === 'other' ? 'Please review this image.' : null);
        }

        $this->assertDatabaseCount('community_photo_reports', 5);
        $this->assertDatabaseHas('community_photo_reports', ['community_photo_id' => $photo->id, 'reason' => 'in_photo', 'status' => 'open']);
    }

    public function test_reports_reject_non_public_or_unsafe_photos_without_disclosing_their_state(): void
    {
        $pending = $this->publishedPhoto(['moderation_status' => 'pending', 'published_at' => null]);
        $unsafe = $this->publishedPhoto();
        $unsafe->update(['processed_variants' => []]);

        foreach ([$pending, $unsafe] as $photo) {
            try {
                app(SubmitCommunityPhotoReport::class)->handle($photo, 'privacy');
                $this->fail('Expected a non-disclosing report rejection.');
            } catch (ValidationException $exception) {
                $this->assertSame(['photo' => ['This photo is not available for reporting.']], $exception->errors());
            }
        }

        $this->assertDatabaseCount('community_photo_reports', 0);
    }

    public function test_other_report_reason_requires_bounded_detail(): void
    {
        $photo = $this->publishedPhoto();

        $this->expectException(ValidationException::class);
        app(SubmitCommunityPhotoReport::class)->handle($photo, 'other');
    }

    public function test_a_published_uploader_gets_one_idempotent_open_removal_request_but_cannot_delete_history(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id]);

        $first = app(RequestCommunityPhotoRemoval::class)->handle($uploader, $photo, 'Please remove this.');
        $second = app(RequestCommunityPhotoRemoval::class)->handle($uploader, $photo, 'Changed wording is ignored.');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('community_photo_removal_requests', 1);
        $this->expectException(ValidationException::class);
        app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
    }

    public function test_only_the_active_pending_uploader_can_delete_and_the_delete_cancels_processing_and_media_safely(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null, 'processing_status' => 'queued']);
        CommunityPhotoProcessingJob::query()->create([
            'community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0,
            'staged_source_path' => $photo->source_path, 'available_at' => now(),
        ]);
        $other = User::factory()->create();

        try {
            app(DeletePendingCommunityPhoto::class)->handle($other, $photo);
            $this->fail('Expected unauthorized pending-photo deletion.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id]);
        }

        app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);

        $this->assertDatabaseMissing('community_photos', ['id' => $photo->id]);
        $this->assertDatabaseMissing('community_photo_processing_jobs', ['community_photo_id' => $photo->id]);
        Storage::disk('local')->assertMissing($photo->source_path);
    }

    public function test_a_failed_pending_photo_storage_delete_keeps_the_cancelled_job_and_photo_for_safe_retry(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null]);
        $outputDirectory = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301';
        Storage::disk('local')->put($outputDirectory.'/master.jpg', 'processed');
        CommunityPhotoProcessingJob::query()->create(['community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0, 'staged_source_path' => $photo->source_path, 'output_directory' => $outputDirectory]);
        Storage::shouldReceive('disk->delete')->andReturnFalse();

        try {
            app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
            $this->fail('Expected failed storage cleanup.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'processing_status' => 'deleting']);
            $this->assertDatabaseHas('community_photo_processing_jobs', ['community_photo_id' => $photo->id, 'status' => 'cancelled']);
            $this->assertDatabaseHas('community_photo_processing_jobs', ['community_photo_id' => $photo->id, 'output_directory' => $outputDirectory]);
        }
    }

    public function test_a_failed_output_directory_delete_keeps_all_cleanup_references_for_a_safe_retry(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null]);
        $outputDirectory = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301';
        CommunityPhotoProcessingJob::query()->create(['community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0, 'staged_source_path' => $photo->source_path, 'output_directory' => $outputDirectory]);

        Storage::shouldReceive('disk->delete')->andReturnTrue();
        Storage::shouldReceive('disk->deleteDirectory')->once()->with($outputDirectory)->andReturnFalse();

        try {
            app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
            $this->fail('Expected failed output-directory cleanup.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'processing_status' => 'deleting']);
            $this->assertDatabaseHas('community_photo_processing_jobs', ['community_photo_id' => $photo->id, 'status' => 'cancelled', 'staged_source_path' => $photo->source_path, 'output_directory' => $outputDirectory]);
        }
    }

    public function test_a_cancelled_pending_delete_cannot_be_finalised_by_a_deferred_worker(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null, 'processing_status' => 'queued']);
        $job = CommunityPhotoProcessingJob::query()->create(['community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0, 'staged_source_path' => $photo->source_path]);
        Storage::shouldReceive('disk->delete')->andReturnFalse();

        try {
            app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
            $this->fail('Expected a durable cleanup retry state.');
        } catch (\RuntimeException) {
            $this->assertFalse(app(ProcessDeferredCommunityPhotos::class)->process($job->id));
            $this->assertDatabaseHas('community_photo_processing_jobs', ['id' => $job->id, 'status' => 'cancelled']);
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'processing_status' => 'deleting']);
        }
    }

    public function test_unsafe_persisted_pending_delete_references_are_never_filtered_or_deleted(): void
    {
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null]);
        $unrelated = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/unrelated.jpg';
        Storage::disk('local')->put($unrelated, 'do not delete');
        DB::table('community_photos')->where('id', $photo->id)->update(['source_path' => '../unsafe.jpg', 'processed_variants' => json_encode(['master' => $unrelated])]);
        $job = CommunityPhotoProcessingJob::query()->create(['community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0, 'staged_source_path' => '../staged.jpg', 'output_directory' => '../output']);

        try {
            app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
            $this->fail('Expected unsafe persisted references to require repair.');
        } catch (\RuntimeException) {
            Storage::disk('local')->assertExists($unrelated);
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'source_path' => '../unsafe.jpg', 'processing_status' => 'deleting']);
            $this->assertDatabaseHas('community_photo_processing_jobs', ['id' => $job->id, 'status' => 'cancelled', 'staged_source_path' => '../staged.jpg', 'output_directory' => '../output']);
        }
    }

    public function test_report_limiter_is_bounded_per_anonymous_identity_and_decays(): void
    {
        $photo = $this->publishedPhoto();
        RateLimiter::clear('photo-report:account:ip:127.0.0.1');
        RateLimiter::clear('photo-report:ip:127.0.0.1');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('community-photos.reports.store', $photo), ['reason' => 'privacy', 'website' => ''])->assertRedirect();
        }
        $this->post(route('community-photos.reports.store', $photo), ['reason' => 'privacy', 'website' => ''])->assertStatus(429);

        $this->travel(61)->seconds();
        $this->post(route('community-photos.reports.store', $photo), ['reason' => 'privacy', 'website' => ''])->assertRedirect();
    }

    public function test_http_report_rejection_has_the_same_generic_response_for_private_and_missing_photos(): void
    {
        $pending = $this->publishedPhoto(['moderation_status' => 'pending', 'published_at' => null]);

        $private = $this->post(route('community-photos.reports.store', $pending), ['reason' => 'privacy', 'website' => '']);
        $missing = $this->post('/photos/999999/report', ['reason' => 'privacy', 'website' => '']);

        $private->assertRedirect()->assertSessionHas('status', 'Thank you. Your report has been received.');
        $missing->assertRedirect()->assertSessionHas('status', 'Thank you. Your report has been received.');
    }

    public function test_open_report_queue_uses_the_same_own_event_scope_and_excludes_special_albums_for_organisers(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $own = $this->publishedPhoto(['event_id' => Event::factory()->for($leader, 'organiser')->create()->id]);
        $other = $this->publishedPhoto();
        app(SubmitCommunityPhotoReport::class)->handle($own, 'privacy');
        app(SubmitCommunityPhotoReport::class)->handle($other, 'privacy');

        $ids = app(ModeratableCommunityPhotoReports::class)->for($leader)->pluck('community_photo_id')->all();

        $this->assertSame([$own->id], $ids);
    }

    public function test_an_authorised_moderator_can_explicitly_dismiss_an_open_report_once(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $this->assertTrue($moderator->hasCapability(ModuleCapability::ModerateAllCommunityPhotos));
        $photo = $this->publishedPhoto();
        $report = app(SubmitCommunityPhotoReport::class)->handle($photo, 'privacy');
        $this->assertTrue(app(ModeratableCommunityPhotoReports::class)->for($moderator)->whereKey($report->id)->exists());

        app(ResolveCommunityPhotoReport::class)->handle($moderator, $report, 'dismissed');
        app(ResolveCommunityPhotoReport::class)->handle($moderator, $report, 'dismissed');

        $this->assertDatabaseHas('community_photo_reports', ['id' => $report->id, 'status' => 'dismissed', 'resolved_by_user_id' => $moderator->id]);
        $this->assertDatabaseHas('community_photo_moderation_audits', ['community_photo_id' => $photo->id, 'actor_user_id' => $moderator->id, 'action' => 'report_dismissed']);
    }

    public function test_an_authorised_moderator_can_review_an_uploader_removal_request_once(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id]);
        $request = app(RequestCommunityPhotoRemoval::class)->handle($uploader, $photo);

        app(ResolveCommunityPhotoRemovalRequest::class)->handle($moderator, $request, 'reviewed');

        $this->assertDatabaseHas('community_photo_removal_requests', ['id' => $request->id, 'status' => 'reviewed', 'resolved_by_user_id' => $moderator->id]);
        $this->assertDatabaseHas('community_photo_moderation_audits', ['community_photo_id' => $photo->id, 'actor_user_id' => $moderator->id, 'action' => 'removal_request_reviewed']);
    }

    public function test_authorised_moderator_removes_photo_and_resolves_open_report_atomically_with_audit(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $photo = $this->publishedPhoto();
        $report = app(SubmitCommunityPhotoReport::class)->handle($photo, 'privacy');

        app(ResolveCommunityPhotoReport::class)->removePhoto($moderator, $report);

        $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'moderation_status' => 'removed', 'published_at' => null]);
        $this->assertDatabaseHas('community_photo_reports', ['id' => $report->id, 'status' => 'reviewed', 'resolved_by_user_id' => $moderator->id]);
        $this->assertDatabaseHas('community_photo_moderation_audits', ['community_photo_id' => $photo->id, 'actor_user_id' => $moderator->id, 'action' => 'removed']);
        $this->assertDatabaseHas('community_photo_moderation_audits', ['community_photo_id' => $photo->id, 'actor_user_id' => $moderator->id, 'action' => 'report_removed_photo']);
    }

    public function test_authorised_moderator_removes_photo_and_resolves_open_uploader_request_atomically_with_audit(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id]);
        $request = app(RequestCommunityPhotoRemoval::class)->handle($uploader, $photo);

        app(ResolveCommunityPhotoRemovalRequest::class)->removePhoto($moderator, $request);

        $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'moderation_status' => 'removed', 'published_at' => null]);
        $this->assertDatabaseHas('community_photo_removal_requests', ['id' => $request->id, 'status' => 'reviewed', 'resolved_by_user_id' => $moderator->id]);
        $this->assertDatabaseHas('community_photo_moderation_audits', ['community_photo_id' => $photo->id, 'actor_user_id' => $moderator->id, 'action' => 'removal_request_removed_photo']);
    }

    public function test_own_event_moderators_cannot_forge_queue_resolution_for_another_event_or_special_album(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $other = $this->publishedPhoto();
        $special = $this->publishedPhoto(['event_id' => null, 'special_album_id' => SpecialAlbum::query()->create(['title' => 'Global album', 'slug' => 'global-album'])->id]);
        $otherReport = app(SubmitCommunityPhotoReport::class)->handle($other, 'privacy');
        $specialReport = app(SubmitCommunityPhotoReport::class)->handle($special, 'privacy');

        foreach ([$otherReport, $specialReport] as $report) {
            try {
                app(ResolveCommunityPhotoReport::class)->handle($leader, $report, 'reviewed');
                $this->fail('A forged out-of-scope report resolution was accepted.');
            } catch (AuthorizationException) {
                $this->assertDatabaseHas('community_photo_reports', ['id' => $report->id, 'status' => 'open']);
            }
        }
    }

    public function test_active_uploader_can_manage_only_their_own_pending_and_published_photos_from_their_photos_section(): void
    {
        $uploader = User::factory()->create(['email_verified_at' => now()]);
        $pending = $this->publishedPhoto(['uploader_id' => $uploader->id, 'moderation_status' => 'pending', 'published_at' => null, 'caption' => 'My pending photo']);
        $published = $this->publishedPhoto(['uploader_id' => $uploader->id, 'caption' => 'My published photo']);
        $other = $this->publishedPhoto(['caption' => 'Other uploader photo']);

        $this->actingAs($uploader)->get(route('community-photos.upload.create'))
            ->assertOk()->assertSeeText('Your photos')->assertSeeText($pending->caption)->assertSeeText($published->caption)
            ->assertDontSeeText($other->caption);
        $this->actingAs($uploader)->delete(route('community-photos.destroy', $pending))->assertRedirect();
        $this->assertDatabaseMissing('community_photos', ['id' => $pending->id]);
        $this->actingAs($uploader)->post(route('community-photos.removal-request.store', $published), ['detail' => 'Please remove this.'])->assertRedirect();
        $this->assertDatabaseHas('community_photo_removal_requests', ['community_photo_id' => $published->id, 'requester_user_id' => $uploader->id]);
    }

    private function publishedPhoto(array $overrides = []): CommunityPhoto
    {
        $path = 'community-photos/3f2504e0-4f89-41d3-9a0c-'.str_pad((string) (CommunityPhoto::query()->count() + 1), 12, '0', STR_PAD_LEFT).'/master.jpg';
        Storage::disk('local')->put($path, 'safe preview');

        return CommunityPhoto::query()->create(array_replace([
            'event_id' => Event::factory()->create()->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
            'source_path' => $path, 'processed_variants' => ['master' => $path],
            'moderation_status' => 'approved', 'published_at' => now(), 'caption' => 'Reported photo',
        ], $overrides));
    }
}
