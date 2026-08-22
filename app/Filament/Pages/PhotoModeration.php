<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoRemovalRequest;
use App\Domain\Gallery\Actions\ResolveCommunityPhotoReport;
use App\Domain\Gallery\CommunityPhotoModerationPreviewResolver;
use App\Domain\Gallery\Data\CommunityPhotoModerationPreview;
use App\Domain\Gallery\Data\CommunityPhotoModerationRequest;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoRemovalRequest;
use App\Domain\Gallery\Models\CommunityPhotoReport;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoRemovalRequests;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoReports;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
use App\Domain\Gallery\Queries\UploadablePublicEvents;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Pagination\LengthAwarePaginator;

final class PhotoModeration extends Page
{
    protected static ?string $title = 'Photo moderation';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Photo moderation';

    protected string $view = 'filament.pages.photo-moderation';

    /** @var array<int, int> */
    public array $selectedPhotoIds = [];

    public ?int $editingPhotoId = null;

    public string $caption = '';

    public string $photographerName = '';

    public string $targetContext = '';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'photo-moderation';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->hasCapability(ModuleCapability::ModerateOwnEventPhotos)
            || $user->hasCapability(ModuleCapability::ModerateAllCommunityPhotos));
    }

    /** @return LengthAwarePaginator<int, CommunityPhoto> */
    public function pendingPhotos(): LengthAwarePaginator
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ModeratableCommunityPhotos::class)->for($actor)->paginate(20);
    }

    /** @return LengthAwarePaginator<int, CommunityPhoto> */
    public function approvedPhotos(): LengthAwarePaginator
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ModeratableCommunityPhotos::class)->for($actor, ['approved'])->paginate(10, ['*'], 'publishedPage');
    }

    /** @return LengthAwarePaginator<int, CommunityPhotoReport> */
    public function openReports(): LengthAwarePaginator
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ModeratableCommunityPhotoReports::class)->for($actor)->paginate(20, ['*'], 'reportPage');
    }

    public function resolveReport(int $reportId, string $status): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ResolveCommunityPhotoReport::class)->handle($actor, CommunityPhotoReport::query()->findOrFail($reportId), $status);
        Notification::make()->success()->title('Report '.$status)->send();
    }

    public function removeReportedPhoto(int $reportId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ResolveCommunityPhotoReport::class)->removePhoto($actor, CommunityPhotoReport::query()->findOrFail($reportId));
        Notification::make()->success()->title('Reported photo removed')->send();
    }

    /** @return LengthAwarePaginator<int, CommunityPhotoRemovalRequest> */
    public function openRemovalRequests(): LengthAwarePaginator
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ModeratableCommunityPhotoRemovalRequests::class)->for($actor)->paginate(20, ['*'], 'removalRequestPage');
    }

    public function resolveRemovalRequest(int $requestId, string $status): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ResolveCommunityPhotoRemovalRequest::class)->handle($actor, CommunityPhotoRemovalRequest::query()->findOrFail($requestId), $status);
        Notification::make()->success()->title('Removal request '.$status)->send();
    }

    public function removeRequestedPhoto(int $requestId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ResolveCommunityPhotoRemovalRequest::class)->removePhoto($actor, CommunityPhotoRemovalRequest::query()->findOrFail($requestId));
        Notification::make()->success()->title('Requested photo removed')->send();
    }

    public function previewFor(CommunityPhoto $photo): ?CommunityPhotoModerationPreview
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(CommunityPhotoModerationPreviewResolver::class)->resolve($actor, $photo);
    }

    public function processingState(CommunityPhoto $photo): ?string
    {
        return match ($photo->processing_status) {
            'staging', 'queued' => 'Processing queued',
            'processing' => 'Processing in progress',
            'retry' => 'Processing retrying',
            'terminal_failed', 'failed' => 'Processing failed',
            default => $photo->processing_status === 'complete' ? null : 'Processing unavailable',
        };
    }

    public function approve(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->approve($actor, CommunityPhoto::query()->findOrFail($photoId));

        Notification::make()->success()->title('Photo approved')->send();
    }

    public function reject(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->reject($actor, CommunityPhoto::query()->findOrFail($photoId));

        Notification::make()->success()->title('Photo rejected')->send();
    }

    public function bulkApprove(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->bulkApprove($actor, array_map('intval', $this->selectedPhotoIds));
        $this->selectedPhotoIds = [];
        Notification::make()->success()->title('Selected photos approved')->send();
    }

    public function bulkReject(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->bulkReject($actor, array_map('intval', $this->selectedPhotoIds));
        $this->selectedPhotoIds = [];
        Notification::make()->success()->title('Selected photos rejected')->send();
    }

    public function edit(int $photoId, ?string $caption, ?string $photographerName): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->edit($actor, CommunityPhoto::query()->findOrFail($photoId), new CommunityPhotoModerationRequest($caption, $photographerName));
    }

    public function move(int $photoId, string $target): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->move($actor, CommunityPhoto::query()->findOrFail($photoId), $target);
    }

    public function rotate(int $photoId, int $degrees): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->rotate($actor, CommunityPhoto::query()->findOrFail($photoId), $degrees);
    }

    public function remove(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->remove($actor, CommunityPhoto::query()->findOrFail($photoId));
    }

    public function feature(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->feature($actor, CommunityPhoto::query()->findOrFail($photoId));
    }

    public function unfeature(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->unfeature($actor, CommunityPhoto::query()->findOrFail($photoId));
        Notification::make()->success()->title('Featured memory removed')->send();
    }

    public function setManualSortOrder(int $photoId, ?int $sortOrder): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        app(ModerateCommunityPhoto::class)->setManualSortOrder($actor, CommunityPhoto::query()->findOrFail($photoId), $sortOrder);
        Notification::make()->success()->title('Gallery order saved')->send();
    }

    public function beginEditing(int $photoId): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $photo = app(ModeratableCommunityPhotos::class)->for($actor, ['pending', 'approved'])->findOrFail($photoId);
        $this->editingPhotoId = $photo->id;
        $this->caption = (string) $photo->caption;
        $this->photographerName = (string) $photo->photographer_name;
        $this->targetContext = $photo->event_id !== null ? 'event:'.$photo->event_id : 'album:'.$photo->special_album_id;
    }

    public function saveEditing(): void
    {
        if ($this->editingPhotoId === null) {
            return;
        }
        /** @var User $actor */
        $actor = auth()->user();
        $photo = CommunityPhoto::query()->findOrFail($this->editingPhotoId);
        app(ModerateCommunityPhoto::class)->editAndMove($actor, $photo, new CommunityPhotoModerationRequest($this->caption, $this->photographerName), $this->targetContext);
        $this->editingPhotoId = null;
        Notification::make()->success()->title('Photo details saved')->send();
    }

    /** @return array<string, string> */
    public function contextOptions(): array
    {
        /** @var User $actor */
        $actor = auth()->user();
        $events = app(UploadablePublicEvents::class)->query()
            ->when(! $actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos), fn ($query) => $query->where('organiser_id', $actor->id))
            ->orderBy('starts_at')->limit(100)->pluck('title', 'id')
            ->mapWithKeys(fn (string $title, int $id): array => ['event:'.$id => 'Event: '.$title]);
        if (! $actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return $events->all();
        }

        return $events->merge(SpecialAlbum::query()->orderBy('title')->limit(100)->pluck('title', 'id')->mapWithKeys(fn (string $title, int $id): array => ['album:'.$id => 'Album: '.$title]))->all();
    }
}
