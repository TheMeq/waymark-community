<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Data\CommunityPhotoModerationRequest;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;

final class PhotoModeration extends Page
{
    protected static ?string $title = 'Photo moderation';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Photo moderation';

    protected string $view = 'filament.pages.photo-moderation';

    /** @var array<int, int> */
    public array $selectedPhotoIds = [];

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

    /** @return Collection<int, CommunityPhoto> */
    public function pendingPhotos(): Collection
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(ModeratableCommunityPhotos::class)->for($actor)->get();
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
}
