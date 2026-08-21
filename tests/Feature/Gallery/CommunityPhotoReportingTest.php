<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\DeletePendingCommunityPhoto;
use App\Domain\Gallery\Actions\RequestCommunityPhotoRemoval;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoRemovalRequest;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoReport;
use App\Domain\Gallery\Actions\SubmitCommunityPhotoReport;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoProcessingJob;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoReports;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        CommunityPhotoProcessingJob::query()->create(['community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0, 'staged_source_path' => $photo->source_path]);
        Storage::shouldReceive('disk->delete')->andReturnFalse();

        try {
            app(DeletePendingCommunityPhoto::class)->handle($uploader, $photo);
            $this->fail('Expected failed storage cleanup.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('community_photos', ['id' => $photo->id, 'processing_status' => 'deleting']);
            $this->assertDatabaseHas('community_photo_processing_jobs', ['community_photo_id' => $photo->id, 'status' => 'cancelled']);
        }
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
    }

    public function test_an_authorised_moderator_can_review_an_uploader_removal_request_once(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $uploader = User::factory()->create();
        $photo = $this->publishedPhoto(['uploader_id' => $uploader->id]);
        $request = app(RequestCommunityPhotoRemoval::class)->handle($uploader, $photo);

        app(ResolveCommunityPhotoRemovalRequest::class)->handle($moderator, $request, 'reviewed');

        $this->assertDatabaseHas('community_photo_removal_requests', ['id' => $request->id, 'status' => 'reviewed', 'resolved_by_user_id' => $moderator->id]);
    }

    private function publishedPhoto(array $overrides = []): CommunityPhoto
    {
        $path = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c'.str_pad((string) (CommunityPhoto::query()->count() + 1), 4, '0', STR_PAD_LEFT).'/master.jpg';
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
