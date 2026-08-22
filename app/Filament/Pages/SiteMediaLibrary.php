<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\SiteMedia\Actions\DeleteSiteMedia;
use App\Domain\SiteMedia\Actions\PromoteCommunityPhotoToSiteMedia;
use App\Domain\SiteMedia\Actions\UpdateSiteMediaMetadata;
use App\Domain\SiteMedia\Actions\UploadSiteMedia;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Data\SiteMediaPresentation;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class SiteMediaLibrary extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Media library';

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Media library';

    protected string $view = 'filament.pages.site-media-library';

    public ?int $editingMediaId = null;

    public string $altText = '';

    public bool $isDecorative = false;

    public float $focalPointX = 0.5;

    public float $focalPointY = 0.5;

    public ?TemporaryUploadedFile $upload = null;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'media-library';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ModerateAllCommunityPhotos);
    }

    /** @return LengthAwarePaginator<int, SiteMedia> */
    public function media(): LengthAwarePaginator
    {
        return SiteMedia::query()->latest()->paginate(20);
    }

    /** @return LengthAwarePaginator<int, CommunityPhoto> */
    public function promotablePhotos(): LengthAwarePaginator
    {
        return CommunityPhoto::query()->where('moderation_status', 'approved')->where('processing_status', 'complete')->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->paginate(10, ['*'], 'photosPage');
    }

    public function preview(SiteMedia $media): ?SiteMediaPresentation
    {
        return app(SiteMediaPresenter::class)->present($media);
    }

    public function beginEditing(int $id): void
    {
        $media = SiteMedia::query()->findOrFail($id);
        $this->editingMediaId = $media->id;
        $this->altText = (string) $media->alt_text;
        $this->isDecorative = $media->is_decorative;
        $this->focalPointX = (float) $media->focal_point_x;
        $this->focalPointY = (float) $media->focal_point_y;
    }

    public function saveMetadata(): void
    {
        if ($this->editingMediaId === null) {
            return;
        } $actor = auth()->user();
        app(UpdateSiteMediaMetadata::class)->handle($actor, SiteMedia::query()->findOrFail($this->editingMediaId), new SiteMediaMetadata($this->altText, $this->isDecorative, $this->focalPointX, $this->focalPointY));
        $this->editingMediaId = null;
        Notification::make()->success()->title('Media details saved')->send();
    }

    public function promote(int $photoId): void
    {
        $actor = auth()->user();
        $photo = CommunityPhoto::query()->findOrFail($photoId);
        app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata($photo->caption ?: 'Community photo', false, (float) ($photo->focal_point_x ?? 0.5), (float) ($photo->focal_point_y ?? 0.5)));
        Notification::make()->success()->title('Photo promoted to the media library')->send();
    }

    public function remove(int $mediaId): void
    {
        $actor = auth()->user();
        app(DeleteSiteMedia::class)->handle($actor, SiteMedia::query()->findOrFail($mediaId));
        Notification::make()->success()->title('Media removed')->send();
    }

    public function uploadMedia(): void
    {
        $this->validate(['upload' => ['required', 'file']]);
        /** @var User $actor */
        $actor = auth()->user();
        app(UploadSiteMedia::class)->handle($actor, $this->upload, new SiteMediaMetadata($this->altText, $this->isDecorative, $this->focalPointX, $this->focalPointY));
        $this->reset('upload', 'altText', 'isDecorative', 'focalPointX', 'focalPointY');
        $this->focalPointX = 0.5;
        $this->focalPointY = 0.5;
        Notification::make()->success()->title('Media uploaded')->send();
    }
}
