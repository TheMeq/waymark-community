<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Data\CommunityPhotoModerationRequest;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
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
        $events = Event::query()->when(! $actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos), fn ($query) => $query->where('organiser_id', $actor->id))->orderBy('starts_at')->limit(100)->pluck('title', 'id')->mapWithKeys(fn (string $title, int $id): array => ['event:'.$id => 'Event: '.$title]);
        if (! $actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return $events->all();
        }

        return $events->merge(SpecialAlbum::query()->orderBy('title')->limit(100)->pluck('title', 'id')->mapWithKeys(fn (string $title, int $id): array => ['album:'.$id => 'Album: '.$title]))->all();
    }
}
