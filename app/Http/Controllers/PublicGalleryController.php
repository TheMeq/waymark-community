<?php

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use App\Domain\Gallery\Queries\PublicCommunityPhotos;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class PublicGalleryController
{
    public function index(Request $request, PublicCommunityPhotos $photos): View
    {
        return $this->view('Gallery', $photos->recent((int) $request->integer('page', 1)), null, $photos->contexts());
    }

    public function event(Request $request, Event $event, PublicCommunityPhotos $photos): View
    {
        return $this->view($event->title, $photos->forEvent($event->id, (int) $request->integer('page', 1)), ['label' => $event->title, 'url' => route('gallery.events.show', $event)]);
    }

    public function album(Request $request, SpecialAlbum $album, PublicCommunityPhotos $photos): View
    {
        return $this->view($album->title, $photos->forAlbum($album->id, (int) $request->integer('page', 1)), ['label' => $album->title, 'url' => route('gallery.albums.show', $album)]);
    }

    public function show(CommunityPhoto $photo, PublicCommunityPhotoPresenter $presenter): View
    {
        $photo->loadMissing(['event:id,title,slug', 'specialAlbum:id,title,slug']);
        $presentation = $presenter->present($photo, 'large');
        abort_unless($presentation !== null, 404);

        return view('gallery.show', ['photo' => $presentation, 'downloadsEnabled' => (bool) config('gallery.public.downloads_enabled'), 'theme' => $this->theme(), 'site' => $this->site()]);
    }

    public function image(CommunityPhoto $photo, string $variant, PublicCommunityPhotoPresenter $presenter): Response
    {
        return $this->stream($photo, $variant, false, $presenter);
    }

    public function download(CommunityPhoto $photo, PublicCommunityPhotoPresenter $presenter): Response
    {
        abort_unless((bool) config('gallery.public.downloads_enabled'), 404);

        return $this->stream($photo, 'large', true, $presenter);
    }

    private function stream(CommunityPhoto $photo, string $variant, bool $attachment, PublicCommunityPhotoPresenter $presenter): Response
    {
        $path = $presenter->pathFor($photo, $variant);
        abort_unless($path !== null, 404);
        $disk = Storage::disk($photo->storage_disk);
        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $contents = $disk->get($path);
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=86400, immutable', 'X-Content-Type-Options' => 'nosniff'];
        if ($attachment) {
            $headers['Content-Disposition'] = 'attachment; filename="waymark-photo-'.$photo->id.'.'.($mime === 'image/png' ? 'png' : 'jpg').'"';
        }

        return response($contents, 200, $headers);
    }

    /** @param LengthAwarePaginator<int, CommunityPhoto> $photos @param array{label:string,url:string}|null $context */
    private function view(string $title, LengthAwarePaginator $photos, ?array $context, ?Collection $contexts = null): View
    {
        return view('gallery.index', ['title' => $title, 'photos' => $photos, 'context' => $context, 'contexts' => $contexts ?? collect(), 'presenter' => app(PublicCommunityPhotoPresenter::class), 'theme' => $this->theme(), 'site' => $this->site()]);
    }

    private function theme(): BrandTheme
    {
        return BrandTheme::fromSiteProfile(SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile);
    }

    /** @return array{name:string} */
    private function site(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID);

        return ['name' => $profile?->group_name ?? 'Waymark Community'];
    }
}
