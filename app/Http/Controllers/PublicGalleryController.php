<?php

namespace App\Http\Controllers;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\PublicCommunityPhotoPage;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use App\Domain\Gallery\Queries\HolidayCommunityPhotos;
use App\Domain\Gallery\Queries\PublicCommunityPhotos;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicGalleryController
{
    public function __construct(private readonly PublicCommunityPhotoPresenter $presenter) {}

    public function index(Request $request, PublicCommunityPhotos $photos): View
    {
        return $this->view('Gallery', $photos->recent($request->string('cursor')->toString()), null, $photos->contexts());
    }

    public function event(Request $request, Event $event, PublicCommunityPhotos $photos): View
    {
        abort_unless($this->isCurrentPublicEvent($event), 404);

        return $this->view($event->title, $photos->forEvent($event->id, $request->string('cursor')->toString()), ['label' => $event->title, 'url' => route('gallery.events.show', $event->slug)]);
    }

    public function holiday(Request $request, Event $event, HolidayCommunityPhotos $photos): View
    {
        abort_unless($event->type === EventType::Holiday && $event->holiday !== null && $this->isCurrentPublicEvent($event), 404);

        return $this->view($event->title, $photos->forHoliday($event, $request->string('cursor')->toString()), ['label' => $event->title, 'url' => route('holidays.show', $event->slug)]);
    }

    public function album(Request $request, SpecialAlbum $album, PublicCommunityPhotos $photos): View
    {
        return $this->view($album->title, $photos->forAlbum($album->id, $request->string('cursor')->toString()), ['label' => $album->title, 'url' => route('gallery.albums.show', $album)]);
    }

    public function show(CommunityPhoto $photo): View
    {
        $photo->loadMissing(['event:id,title,slug', 'specialAlbum:id,title,slug']);
        $presentation = $this->presenter->present($photo, 'large');
        abort_unless($presentation !== null, 404);

        return view('gallery.show', ['photo' => $presentation, 'downloadsEnabled' => (bool) config('gallery.public.downloads_enabled'), 'theme' => $this->theme(), 'site' => $this->site()]);
    }

    public function image(CommunityPhoto $photo, string $variant): StreamedResponse
    {
        return $this->stream($photo, $variant, false);
    }

    public function download(CommunityPhoto $photo): StreamedResponse
    {
        abort_unless((bool) config('gallery.public.downloads_enabled'), 404);

        return $this->stream($photo, 'large', true);
    }

    private function stream(CommunityPhoto $photo, string $variant, bool $attachment): StreamedResponse
    {
        $path = $this->presenter->pathFor($photo, $variant);
        abort_unless($path !== null, 404);
        $disk = Storage::disk($photo->storage_disk);
        $mime = $disk->mimeType($path) ?: 'image/jpeg';
        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($attachment) {
            $headers['Content-Disposition'] = 'attachment; filename="waymark-photo-'.$photo->id.'.'.$this->extensionFor($mime).'"';
        }

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }

    private function isCurrentPublicEvent(Event $event): bool
    {
        return $event->is_public
            && $event->published_at?->lessThanOrEqualTo(now())
            && in_array($event->status, [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled, EventStatus::Completed], true);
    }

    /** @param array{label:string,url:string}|null $context */
    private function view(string $title, PublicCommunityPhotoPage $photos, ?array $context, ?Collection $contexts = null): View
    {
        $contexts = ($contexts ?? collect())->map(fn (array $item): array => [...$item, 'cover' => $this->presenter->present($item['cover'])])->filter(fn (array $item): bool => $item['cover'] !== null)->values();

        return view('gallery.index', ['title' => $title, 'photos' => $photos, 'context' => $context, 'contexts' => $contexts, 'theme' => $this->theme(), 'site' => $this->site()]);
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
